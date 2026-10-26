<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\EvaluacionDelDestinoPort;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\DetectorDeCruces;
use App\Modules\GestionPersonal\Domain\Services\VerificadorDeEvaluacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\ResultadoDeEvaluacion;
use Throwable;

/**
 * Condiciones actuales de una solicitud resoluble, con tres resultados separados: cumple, incumple y no verificable
 * (un error de política, evaluación, estructura o documentos nunca es «cumple»). Una señal pendiente (cruce de
 * periodos) no bloquea: exige el reconocimiento del Administrador. La usan el detalle y la aprobación del servidor.
 */
final class ComprobadorDeCondiciones
{
    public const CUMPLE = 'Cumple';

    public const PENDIENTE = 'Pendiente';

    public const NO_CUMPLE = 'NoCumple';

    public const NO_VERIFICABLE = 'NoVerificable';

    /** Código de error del servidor por cada comprobación que impide aprobar. */
    private const CODIGOS = [
        'vinculo' => 'SIN_VINCULO_VIGENTE',
        'origen' => 'SIN_VINCULO_VIGENTE',
        'documento' => 'DOCUMENTO_REQUERIDO',
        'destino' => 'POSICION_NO_DISPONIBLE',
        'evaluacion' => 'EVALUACION_NO_VERIFICABLE',
    ];

    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly EvaluacionDelDestinoPort $evaluaciones,
        private readonly VerificadorDeEvaluacion $verificador,
        private readonly DetectorDeCruces $detector,
        private readonly ValidadorDeRespaldos $respaldos,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @return array{
     *     comprobaciones: list<array{clave: string, estado: string, texto: string}>,
     *     cruces: list<array{id: int, tipo: string, estado: string, inicio: string, fin: string}>,
     *     evaluacion: array{estado: ResultadoDeEvaluacion, motivo: string|null, contexto: array<string, mixed>|null}|null,
     *     destino: array{puesto: string, unidad: string|null}|null,
     *     origen: array{puesto: string, unidad: string|null}|null,
     *     puede_aprobar: bool,
     *     motivo_no_aprobable: string|null
     * }
     */
    public function evaluar(ContextoDeTenant $contexto, Actuacion $actuacion): array
    {
        $hoy = $contexto->hoy();
        $comprobaciones = [];
        $cruces = [];
        $evaluacion = null;
        $destino = null;
        $origen = null;

        $comprobaciones[] = $this->vinculo($actuacion, $hoy);

        $documento = $this->documento($actuacion);
        if ($documento !== null) {
            $comprobaciones[] = $documento;
        }

        if ($actuacion->tipo->esMovimiento()) {
            $movimiento = $actuacion->movimiento ?? [];
            $comprobaciones[] = $this->origenVigente($actuacion, (string) $movimiento['fecha_efectiva']);
            [$estructural, $destino] = $this->destino($contexto, (int) $movimiento['posicion_destino_id']);
            $comprobaciones[] = $estructural;
            $origen = $this->posicionComoTexto((int) $movimiento['posicion_origen_id']);

            $evaluacion = $this->evaluacion($contexto, $actuacion, $destino['puesto_id'] ?? null);
            $comprobaciones[] = $this->comprobacionDeEvaluacion($evaluacion);
        } elseif ($actuacion->tipo->esSolicitudDeNovedad()) {
            $novedad = $actuacion->novedad ?? [];
            $cruces = $this->detector->cruces(
                (string) $novedad['fecha_inicio'],
                (string) $novedad['fecha_fin'],
                $this->actuaciones->periodosDeNovedades($contexto->tenantId, $actuacion->colaboradorId),
                $actuacion->id,
            );
            $comprobaciones[] = $cruces === []
                ? ['clave' => 'cruce', 'estado' => self::CUMPLE, 'texto' => 'No se cruza con otras solicitudes del colaborador.']
                : ['clave' => 'cruce', 'estado' => self::PENDIENTE, 'texto' => 'El periodo se cruza con otras solicitudes: debes reconocerlo para aprobar.'];
        }

        $impide = null;
        foreach ($comprobaciones as $c) {
            if ($c['estado'] === self::NO_CUMPLE || $c['estado'] === self::NO_VERIFICABLE) {
                $impide = self::CODIGOS[$c['clave']] ?? 'ESTADO_NO_RESOLUBLE';
                break;
            }
        }

        return [
            'comprobaciones' => $comprobaciones,
            'cruces' => $cruces,
            'evaluacion' => $evaluacion,
            'destino' => $destino === null ? null : ['puesto' => $destino['puesto'], 'unidad' => $destino['unidad']],
            'origen' => $origen,
            'puede_aprobar' => $impide === null,
            'motivo_no_aprobable' => $impide,
        ];
    }

    /** @return array{clave: string, estado: string, texto: string} */
    private function vinculo(Actuacion $actuacion, string $hoy): array
    {
        try {
            foreach ($this->vinculos->vinculosDeFicha($actuacion->tenantId, $actuacion->colaboradorId) as $vinculo) {
                if ($vinculo->id === $actuacion->vinculoId && $vinculo->periodo()->contiene($hoy)) {
                    return ['clave' => 'vinculo', 'estado' => self::CUMPLE, 'texto' => 'El colaborador tiene un vínculo laboral vigente.'];
                }
            }
        } catch (Throwable) {
            return ['clave' => 'vinculo', 'estado' => self::NO_VERIFICABLE, 'texto' => 'No se pudo verificar el vínculo laboral.'];
        }

        return ['clave' => 'vinculo', 'estado' => self::NO_CUMPLE, 'texto' => 'El colaborador ya no tiene un vínculo laboral vigente.'];
    }

    /** @return array{clave: string, estado: string, texto: string}|null */
    private function documento(Actuacion $actuacion): ?array
    {
        $parametros = $actuacion->parametros();
        $exigido = $actuacion->tipo->esMovimiento()
            ? ($parametros['documento_adicional_obligatorio'] ?? false) === true
            : ($parametros['documento_obligatorio'] ?? false) === true;
        if (! $exigido) {
            return null;
        }

        try {
            $disponibles = $this->respaldos->disponibles($actuacion->tenantId, $actuacion->colaboradorId, $actuacion->documentoIds);
        } catch (Throwable) {
            return ['clave' => 'documento', 'estado' => self::NO_VERIFICABLE, 'texto' => 'No se pudo verificar el respaldo documental.'];
        }

        return $disponibles >= 1
            ? ['clave' => 'documento', 'estado' => self::CUMPLE, 'texto' => 'El respaldo exigido está disponible.']
            : ['clave' => 'documento', 'estado' => self::NO_CUMPLE, 'texto' => 'El respaldo exigido no está disponible.'];
    }

    /** @return array{clave: string, estado: string, texto: string} */
    private function origenVigente(Actuacion $actuacion, string $fechaEfectiva): array
    {
        $origenId = (int) ($actuacion->movimiento['asignacion_origen_id'] ?? 0);

        try {
            foreach ($this->vinculos->vinculosDeFicha($actuacion->tenantId, $actuacion->colaboradorId) as $vinculo) {
                foreach ($vinculo->asignaciones as $asignacion) {
                    if ($asignacion->id === $origenId && $asignacion->fechaFin === null && $asignacion->fechaInicio < $fechaEfectiva) {
                        return ['clave' => 'origen', 'estado' => self::CUMPLE, 'texto' => 'La posición actual sigue siendo la de la propuesta.'];
                    }
                }
            }
        } catch (Throwable) {
            return ['clave' => 'origen', 'estado' => self::NO_VERIFICABLE, 'texto' => 'No se pudo verificar la posición actual.'];
        }

        return ['clave' => 'origen', 'estado' => self::NO_CUMPLE, 'texto' => 'La posición actual cambió desde la propuesta o la fecha efectiva no es posterior a su inicio.'];
    }

    /**
     * @return array{0: array{clave: string, estado: string, texto: string}, 1: array{puesto_id: int|null, puesto: string, unidad: string|null}|null}
     */
    private function destino(ContextoDeTenant $contexto, int $posicionId): array
    {
        try {
            $veredicto = $this->posiciones->verificar($posicionId, $contexto->tenantId);
        } catch (Throwable) {
            return [['clave' => 'destino', 'estado' => self::NO_VERIFICABLE, 'texto' => 'No se pudo verificar la posición destino con Estructura.'], null];
        }

        $posicion = $veredicto['posicion'] ?? null;
        $datos = $posicion === null || $posicion['id'] === 0 ? null : [
            'puesto_id' => ($posicion['puesto_id'] ?? 0) > 0 ? (int) $posicion['puesto_id'] : null,
            'puesto' => (string) $posicion['puesto'],
            'unidad' => $posicion['unidad'] ?? null,
        ];

        return $veredicto['asignable']
            ? [['clave' => 'destino', 'estado' => self::CUMPLE, 'texto' => 'La posición destino admite asignaciones.'], $datos]
            : [['clave' => 'destino', 'estado' => self::NO_CUMPLE, 'texto' => 'La posición destino no admite asignaciones: '.$this->motivoDeDestino($veredicto['motivo'] ?? null)], $datos];
    }

    /**
     * @return array{estado: ResultadoDeEvaluacion, motivo: string|null, contexto: array<string, mixed>|null}
     */
    private function evaluacion(ContextoDeTenant $contexto, Actuacion $actuacion, ?int $puestoDestinoId): array
    {
        try {
            $respuesta = $this->evaluaciones->consultar(
                $contexto->tenantId,
                $actuacion->colaboradorId,
                (int) ($actuacion->movimiento['posicion_destino_id'] ?? 0),
                $this->reloj->ahora(),
            );
        } catch (Throwable) {
            return ['estado' => ResultadoDeEvaluacion::NoVerificable, 'motivo' => 'La evaluación no pudo verificarse.', 'contexto' => null];
        }

        return $this->verificador->verificar($respuesta, $actuacion->colaboradorId, $puestoDestinoId, $this->reloj->ahora());
    }

    /**
     * @param  array{estado: ResultadoDeEvaluacion, motivo: string|null, contexto: array<string, mixed>|null}  $evaluacion
     * @return array{clave: string, estado: string, texto: string}
     */
    private function comprobacionDeEvaluacion(array $evaluacion): array
    {
        return $evaluacion['estado'] === ResultadoDeEvaluacion::Valida
            ? ['clave' => 'evaluacion', 'estado' => self::CUMPLE, 'texto' => 'Existe una evaluación válida frente al puesto destino; es evidencia para decidir, no una aprobación.']
            : ['clave' => 'evaluacion', 'estado' => self::NO_VERIFICABLE, 'texto' => $evaluacion['motivo'] ?? 'La evaluación no pudo verificarse.'];
    }

    /** @return array{puesto: string, unidad: string|null}|null */
    private function posicionComoTexto(int $posicionId): ?array
    {
        try {
            $posicion = $this->posiciones->describir($posicionId);
        } catch (Throwable) {
            return null;
        }

        return $posicion === null ? null : ['puesto' => $posicion['puesto'], 'unidad' => $posicion['unidad']];
    }

    private function motivoDeDestino(?string $motivo): string
    {
        return match ($motivo) {
            'suprimida' => 'está suprimida.',
            'puesto_inactivo' => 'su puesto está inactivo.',
            'unidad_inactiva' => 'su unidad está inactiva.',
            default => 'no está disponible.',
        };
    }
}
