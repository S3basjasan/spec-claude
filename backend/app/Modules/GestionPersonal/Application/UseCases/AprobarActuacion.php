<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ComprobadorDeCondiciones;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeActuacion;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\CruceSinReconocer;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoRequerido;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoResoluble;
use App\Modules\GestionPersonal\Domain\Exceptions\EvaluacionNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinVinculoVigente;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Aprueba una novedad o un movimiento tras volver a verificar autorización, estado, versión, vínculo, respaldos,
 * destino y, en movimientos, la evaluación frente al puesto destino. La política conservada no se sustituye.
 * Aprobar un movimiento nunca modifica asignaciones: lo deja Programado (fecha futura) o Aprobado (fecha alcanzada).
 */
final class AprobarActuacion
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ComprobadorDeCondiciones $comprobador,
        private readonly TransicionadorDeActuacion $transicionador,
    ) {}

    /**
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $version, mixed $justificacion, bool $reconoceCruce, ?string $clave): ResultadoDeActuacion
    {
        $razon = is_string($justificacion) ? trim($justificacion) : '';
        if ($razon === '' || mb_strlen($razon) > RechazarActuacion::JUSTIFICACION_MAXIMA) {
            throw new DatosInvalidos(['justificacion' => ['La justificación de la aprobación es obligatoria y admite hasta '.RechazarActuacion::JUSTIFICACION_MAXIMA.' caracteres.']]);
        }

        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;

        return $this->transicionador->ejecutar(
            $contexto,
            $userId,
            $previa,
            $version,
            'aprobacion',
            $clave,
            [$razon, $reconoceCruce],
            fn (Actuacion $actual) => $this->decidir($contexto, $userId, $actual, $razon, $reconoceCruce),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decidir(ContextoDeTenant $contexto, int $userId, Actuacion $actual, string $razon, bool $reconoceCruce): array
    {
        if (! $actual->estado->esResoluble() || $actual->tipo->esAusencia()) {
            throw new EstadoNoResoluble;
        }

        $resultado = $this->comprobador->evaluar($contexto, $actual);
        if ($resultado['motivo_no_aprobable'] !== null) {
            throw $this->excepcionDe($resultado);
        }

        $ahora = Carbon::now('UTC')->format('Y-m-d H:i:s');

        if ($actual->tipo->esMovimiento()) {
            $evaluacion = $resultado['evaluacion'];
            $contextoDeEvaluacion = $evaluacion['contexto'] ?? null;

            return [
                'nuevo' => ($actual->fechaEfectiva() ?? '') > $contexto->hoy() ? EstadoDeActuacion::Programada : EstadoDeActuacion::Aprobada,
                'evento' => 'Aprobada',
                'justificacion' => $razon,
                'detalle' => ['evaluacion' => ['estado' => $evaluacion['estado']->value, 'contexto' => $contextoDeEvaluacion]],
                'novedad' => [],
                'movimiento' => [
                    'evaluacion_estado' => $evaluacion['estado']->value,
                    'evaluacion_contexto' => $contextoDeEvaluacion === null ? null : json_encode($contextoDeEvaluacion, JSON_UNESCAPED_UNICODE),
                    'evaluacion_consultada_en' => $ahora,
                    'aprobada_por' => $userId,
                    'aprobada_en' => $ahora,
                ],
            ];
        }

        $cruces = $resultado['cruces'];
        if ($cruces !== [] && ! $reconoceCruce) {
            throw new CruceSinReconocer;
        }

        return [
            'nuevo' => EstadoDeActuacion::Aprobada,
            'evento' => 'Aprobada',
            'justificacion' => $razon,
            'detalle' => $cruces === [] ? [] : ['cruces_reconocidos' => array_map(fn (array $c) => $c['id'], $cruces)],
            'novedad' => $cruces === [] ? [] : ['cruce_reconocido_por' => $userId, 'cruce_reconocido_en' => $ahora],
            'movimiento' => [],
        ];
    }

    /**
     * @param  array{comprobaciones: list<array{clave: string, estado: string, texto: string}>, motivo_no_aprobable: string|null}  $resultado
     */
    private function excepcionDe(array $resultado): RuntimeException
    {
        $fallida = null;
        foreach ($resultado['comprobaciones'] as $c) {
            if ($c['estado'] === ComprobadorDeCondiciones::NO_CUMPLE || $c['estado'] === ComprobadorDeCondiciones::NO_VERIFICABLE) {
                $fallida = $c;
                break;
            }
        }
        $texto = $fallida['texto'] ?? 'No se cumplen las condiciones para aprobar.';

        if ($fallida !== null && $fallida['estado'] === ComprobadorDeCondiciones::NO_VERIFICABLE && $fallida['clave'] !== 'evaluacion') {
            return new IngresoNoVerificable($texto);
        }

        return match ($resultado['motivo_no_aprobable']) {
            'EVALUACION_NO_VERIFICABLE' => new EvaluacionNoVerificable($texto),
            'DOCUMENTO_REQUERIDO' => new DocumentoRequerido($texto),
            'POSICION_NO_DISPONIBLE' => new PosicionNoDisponible($texto),
            'SIN_VINCULO_VIGENTE' => new SinVinculoVigente($texto),
            default => new EstadoNoResoluble($texto),
        };
    }
}
