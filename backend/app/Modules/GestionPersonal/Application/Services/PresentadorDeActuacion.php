<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\DTO\Solicitante;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinVinculoVigente;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\MaquinaDeEstados;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeActuacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;
use Illuminate\Support\Carbon;

/**
 * Presentación formal de una actuación: valida, bloquea la ficha y, en la misma transacción, fija la versión de la
 * política con el mecanismo de coordinación de Configuración de políticas y registra la actuación con la copia de
 * sus condiciones. Presentar nunca modifica vínculos ni asignaciones. Idempotente por `Idempotency-Key`.
 */
final class PresentadorDeActuacion
{
    private const MOTIVOS_DE_DESTINO = [
        'suprimida' => 'La posición destino está suprimida y no admite nuevas asignaciones.',
        'puesto_inactivo' => 'El puesto de la posición destino está inactivo y no admite nuevas asignaciones.',
        'unidad_inactiva' => 'La unidad de la posición destino está inactiva y no admite nuevas asignaciones.',
    ];

    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly PoliticasService $politicas,
        private readonly ValidadorDeRespaldos $respaldos,
        private readonly ControlDeOperaciones $operaciones,
        private readonly ReglasDeActuacion $reglas,
        private readonly MaquinaDeEstados $maquina,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  `fecha_inicio`/`fecha_fin` (novedades), `fecha_efectiva` y `posicion_destino_id` (movimientos), `motivo` y `documento_ids`.
     *
     * @throws DatosInvalidos
     */
    public function presentar(ContextoDeTenant $contexto, Solicitante $solicitante, TipoDeActuacion $tipo, array $datos, ?string $claveDeOperacion): ResultadoDeActuacion
    {
        $clave = $this->operaciones->normalizar($claveDeOperacion);
        $ahora = $this->reloj->ahora();
        $hoy = Carbon::instance($ahora)->setTimezone($contexto->zonaHoraria)->toDateString();

        $errores = $this->reglas->errores($tipo, $datos, $hoy);
        if ($tipo->esMovimiento() && ! $this->esEnteroPositivo($datos['posicion_destino_id'] ?? null)) {
            $errores['posicion_destino_id'][] = 'La posición destino es obligatoria.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $respaldos = $this->respaldos->validar($contexto->tenantId, $solicitante, (array) ($datos['documento_ids'] ?? []));
        $huella = $this->huella($tipo, $solicitante, $datos, $respaldos);

        $previa = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $solicitante->userId);
        if ($previa !== null) {
            return new ResultadoDeActuacion($previa, false);
        }

        try {
            $actuacion = $this->vinculos->ejecutarBajoBloqueo(
                $contexto->tenantId,
                $solicitante->colaboradorId,
                fn () => $this->confirmar($contexto, $solicitante, $tipo, $datos, $respaldos, $clave, $huella, $ahora, $hoy),
            );
        } catch (ConflictoDeUnicidad $conflicto) {
            // La transacción ya se cerró: la carrera idempotente se resuelve con una lectura nueva.
            $previa = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $solicitante->userId);
            if ($previa !== null) {
                return new ResultadoDeActuacion($previa, false);
            }

            throw $conflicto;
        }

        return $actuacion instanceof ResultadoDeActuacion ? $actuacion : new ResultadoDeActuacion($actuacion, true);
    }

    /**
     * Cuerpo de la transacción: ya se tiene el bloqueo de la ficha.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $respaldos
     */
    private function confirmar(ContextoDeTenant $contexto, Solicitante $solicitante, TipoDeActuacion $tipo, array $datos, array $respaldos, string $clave, string $huella, \DateTimeImmutable $ahora, string $hoy): Actuacion|ResultadoDeActuacion
    {
        $previa = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $solicitante->userId);
        if ($previa !== null) {
            return new ResultadoDeActuacion($previa, false);
        }

        $vinculo = $this->vinculoVigente($contexto->tenantId, $solicitante->colaboradorId, $hoy);

        // La política se fija aquí, tras el bloqueo de la ficha y dentro de la misma transacción de la actuación.
        $politica = $this->politicas->seleccionarParaPresentar($contexto->tenantId, $tipo->politica()->value, $ahora);
        $parametros = (array) $politica['parametros'];

        $this->reglas->verificar($tipo, $datos, $parametros, $hoy, count($respaldos));

        $detalle = $tipo->esMovimiento()
            ? $this->detalleDeMovimiento($contexto, $vinculo, $datos, $hoy)
            : [
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'ocurrida' => $tipo->esAusencia() ? 1 : 0,
            ];

        $motivo = trim((string) ($datos['motivo'] ?? ''));

        return $this->actuaciones->insertar([
            'tenant_id' => $contexto->tenantId,
            'colaborador_id' => $solicitante->colaboradorId,
            'vinculo_id' => $vinculo->id,
            'tipo' => $tipo->value,
            'categoria' => $tipo->categoria(),
            'estado' => $this->maquina->inicial($tipo)->value,
            'motivo' => $motivo === '' ? null : $motivo,
            'presentada_por' => $solicitante->userId,
            'en_nombre_de_colaborador' => $solicitante->enNombreDeColaborador ? 1 : 0,
            'presentada_en' => Carbon::instance($ahora)->utc()->format('Y-m-d H:i:s'),
            'fecha_presentacion' => $hoy,
            'politica_version_id' => $politica['version_id'],
            'politica_numero' => $politica['numero'],
            'politica_condiciones' => (string) json_encode($politica['condiciones'], JSON_UNESCAPED_UNICODE),
            'politica_hash' => $politica['hash'],
            'version' => 1,
            'operacion_id' => $clave,
            'operacion_hash' => $huella,
            'created_by' => $solicitante->userId,
            'updated_by' => $solicitante->userId,
        ], $detalle, $respaldos);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function detalleDeMovimiento(ContextoDeTenant $contexto, VinculoLaboral $vinculo, array $datos, string $hoy): array
    {
        $destinoId = (int) $datos['posicion_destino_id'];
        $veredicto = $this->posiciones->verificar($destinoId, $contexto->tenantId);
        if (! $veredicto['asignable']) {
            throw new PosicionNoDisponible(self::MOTIVOS_DE_DESTINO[$veredicto['motivo'] ?? ''] ?? 'La posición destino no está disponible.');
        }

        $origen = $vinculo->asignacionEn($hoy);
        if ($origen === null || $origen->fechaFin !== null) {
            throw new SinVinculoVigente('La persona no tiene una asignación vigente desde la que proponer un movimiento.');
        }
        if ($origen->posicionId === $destinoId) {
            throw new PosicionNoDisponible('La posición destino es la que el colaborador ya ocupa.');
        }
        if ((string) $datos['fecha_efectiva'] <= $origen->fechaInicio) {
            throw new DatosInvalidos(['fecha_efectiva' => ['La fecha efectiva debe ser posterior al inicio de la asignación actual.']]);
        }

        return [
            'asignacion_origen_id' => $origen->id,
            'posicion_origen_id' => $origen->posicionId,
            'posicion_destino_id' => $destinoId,
            'fecha_efectiva' => $datos['fecha_efectiva'],
            'aplicacion_estado' => 'Pendiente',
            'aplicada_por_sistema' => 0,
            'intentos_aplicacion' => 0,
        ];
    }

    private function vinculoVigente(int $tenantId, int $colaboradorId, string $hoy): VinculoLaboral
    {
        foreach ($this->vinculos->vinculosDeFicha($tenantId, $colaboradorId) as $vinculo) {
            if ($vinculo->periodo()->contiene($hoy)) {
                return $vinculo;
            }
        }

        throw new SinVinculoVigente;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $respaldos
     */
    private function huella(TipoDeActuacion $tipo, Solicitante $solicitante, array $datos, array $respaldos): string
    {
        $ordenados = $respaldos;
        sort($ordenados);

        return $this->operaciones->huella(
            'presentar',
            $tipo->value,
            $solicitante->colaboradorId,
            $datos['fecha_inicio'] ?? null,
            $datos['fecha_fin'] ?? null,
            $datos['fecha_efectiva'] ?? null,
            isset($datos['posicion_destino_id']) ? (int) $datos['posicion_destino_id'] : null,
            trim((string) ($datos['motivo'] ?? '')),
            $ordenados,
        );
    }

    private function esEnteroPositivo(mixed $valor): bool
    {
        return (is_int($valor) && $valor > 0) || (is_string($valor) && ctype_digit($valor) && (int) $valor > 0);
    }
}
