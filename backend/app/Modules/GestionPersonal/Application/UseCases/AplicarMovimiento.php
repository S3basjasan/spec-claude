<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Services\ControlDeOperaciones;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\AplicacionNoProcede;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\IncidenciaDeAplicacion;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\VinculoSuperpuesto;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeIngreso;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use Illuminate\Support\Carbon;

/**
 * Aplica un movimiento aprobado: cierra la asignación de origen en la fecha efectiva y abre la nueva, de forma atómica
 * y bajo el bloqueo de la ficha. Lo ejecuta el sistema al llegar la fecha o el Administrador al reintentar una
 * incidencia, con la misma verificación. Si una condición actual lo impide no hay cambios parciales: se registra una
 * incidencia (en una transacción aparte, para que persista) y nunca se comunica como aplicado.
 */
final class AplicarMovimiento
{
    private const MENSAJE_DE_ESTRUCTURA = 'No se pudo verificar la posición destino con Estructura.';

    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly ReglasDeIngreso $reglasDeOcupacion,
        private readonly ControlDeOperaciones $operaciones,
    ) {}

    /**
     * Aplicación manual (o reintento de una incidencia) por un Administrador.
     *
     * @throws SolicitudNoEncontrada
     * @throws SolicitudModificada
     * @throws AplicacionNoProcede
     * @throws IncidenciaDeAplicacion La condición sigue sin cumplirse (la incidencia queda registrada).
     */
    public function comoAdministrador(ContextoDeTenant $contexto, int $userId, int $id, int $version, ?string $clave): ResultadoDeActuacion
    {
        $clave = $this->operaciones->normalizar($clave);
        $huella = $this->operaciones->huella('aplicacion', $id, $version);

        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;
        if (! $previa->tipo->esMovimiento()) {
            throw new AplicacionNoProcede('Solo los movimientos se aplican.');
        }

        $repetida = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $userId, $id);
        if ($repetida !== null) {
            return $this->resultadoDeRepeticion($repetida);
        }

        return $this->ejecutar($contexto, $userId, $previa, $version, $clave, $huella);
    }

    /**
     * Aplicación automática al llegar la fecha efectiva, con identidad de sistema. Idempotente: lo que ya no está
     * pendiente (aplicado en paralelo, con incidencia previa) se deja como está.
     */
    public function comoSistema(ContextoDeTenant $contexto, int $id): ResultadoDeActuacion
    {
        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;

        try {
            return $this->ejecutar($contexto, null, $previa, null, null, null);
        } catch (IncidenciaDeAplicacion) {
            return new ResultadoDeActuacion($this->actuaciones->porId($contexto->tenantId, $id) ?? $previa, true);
        } catch (AplicacionNoProcede|SolicitudModificada|ConflictoDeUnicidad) {
            return new ResultadoDeActuacion($this->actuaciones->porId($contexto->tenantId, $id) ?? $previa, false);
        }
    }

    private function ejecutar(ContextoDeTenant $contexto, ?int $actorId, Actuacion $previa, ?int $version, ?string $clave, ?string $huella): ResultadoDeActuacion
    {
        try {
            return $this->vinculos->ejecutarBajoBloqueo(
                $contexto->tenantId,
                $previa->colaboradorId,
                fn () => $this->aplicarBajoBloqueo($contexto, $actorId, $previa->id, $version, $clave, $huella),
            );
        } catch (IncidenciaDeAplicacion $incidencia) {
            $this->registrarIncidencia($contexto, $actorId, $previa, $incidencia, $clave, $huella);

            throw $incidencia;
        } catch (ConflictoDeUnicidad $conflicto) {
            if ($clave !== null && $huella !== null && $actorId !== null) {
                $repetida = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
                if ($repetida !== null) {
                    return $this->resultadoDeRepeticion($repetida);
                }
            }

            throw $conflicto;
        }
    }

    /** Cuerpo de la transacción: ya se tiene el bloqueo de la ficha, así que todas las lecturas ven el estado vigente. */
    private function aplicarBajoBloqueo(ContextoDeTenant $contexto, ?int $actorId, int $id, ?int $version, ?string $clave, ?string $huella): ResultadoDeActuacion
    {
        $actual = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;

        if ($version !== null && $actual->version !== $version) {
            throw new SolicitudModificada;
        }
        if (! $actual->estado->esPendienteDeAplicar() || ($actorId === null && $actual->estado === EstadoDeActuacion::IncidenciaDeAplicacion)) {
            throw new AplicacionNoProcede('El movimiento no está pendiente de aplicación.');
        }

        $movimiento = $actual->movimiento ?? throw new AplicacionNoProcede;
        $fechaEfectiva = (string) $movimiento['fecha_efectiva'];
        $hoy = $contexto->hoy();
        if ($fechaEfectiva > $hoy) {
            throw new AplicacionNoProcede('La fecha efectiva del movimiento todavía no llegó.');
        }

        $this->verificarCondiciones($contexto, $actual, $movimiento, $fechaEfectiva, $hoy);

        try {
            $nuevaId = $this->vinculos->cerrarYAbrirAsignacion(
                $contexto->tenantId,
                $actual->vinculoId,
                (int) $movimiento['asignacion_origen_id'],
                (int) $movimiento['posicion_destino_id'],
                $fechaEfectiva,
                $actual->id,
                $actorId,
            );
        } catch (PosicionNoDisponible) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::DESTINO_OCUPADO, 'La posición destino está ocupada en el periodo del movimiento.');
        } catch (VinculoSuperpuesto) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::INCONSISTENCIA, 'La asignación resultante se superpone con otra del colaborador.');
        } catch (ConflictoDeUnicidad) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::INCONSISTENCIA, 'El movimiento ya generó una asignación.');
        }

        $ahora = Carbon::now('UTC')->format('Y-m-d H:i:s');
        $nueva = $this->actuaciones->cambiarEstado(
            $actual,
            EstadoDeActuacion::Aplicada,
            'Aplicada',
            $actorId,
            null,
            ['asignacion_nueva_id' => $nuevaId, 'reintento' => $actual->estado === EstadoDeActuacion::IncidenciaDeAplicacion],
            $clave,
            $huella,
            [],
            [
                'aplicacion_estado' => 'Aplicada',
                'aplicada_en' => $ahora,
                'aplicada_por' => $actorId,
                'aplicada_por_sistema' => $actorId === null ? 1 : 0,
                'asignacion_nueva_id' => $nuevaId,
                'incidencia_codigo' => null,
                'incidencia_detalle' => null,
                'intentos_aplicacion' => (int) $movimiento['intentos_aplicacion'] + 1,
            ],
        );

        return new ResultadoDeActuacion($nueva, true);
    }

    /**
     * @param  array<string, mixed>  $movimiento
     *
     * @throws IncidenciaDeAplicacion
     */
    private function verificarCondiciones(ContextoDeTenant $contexto, Actuacion $actual, array $movimiento, string $fechaEfectiva, string $hoy): void
    {
        $vinculos = $this->vinculos->vinculosDeFicha($contexto->tenantId, $actual->colaboradorId);

        $vigente = false;
        $origenAbierto = false;
        foreach ($vinculos as $vinculo) {
            if ($vinculo->id !== $actual->vinculoId) {
                continue;
            }
            $vigente = $vinculo->periodo()->contiene($hoy);
            foreach ($vinculo->asignaciones as $asignacion) {
                if ($asignacion->id === (int) $movimiento['asignacion_origen_id'] && $asignacion->fechaFin === null && $asignacion->fechaInicio < $fechaEfectiva) {
                    $origenAbierto = true;
                }
            }
        }
        if (! $vigente) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::VINCULO_NO_VIGENTE, 'El colaborador ya no tiene un vínculo laboral vigente.');
        }
        if (! $origenAbierto) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::ORIGEN_CAMBIO, 'La asignación de origen cambió desde la propuesta.');
        }

        try {
            $veredicto = $this->posiciones->verificar((int) $movimiento['posicion_destino_id'], $contexto->tenantId);
        } catch (IngresoNoVerificable) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::ESTRUCTURA_NO_VERIFICABLE, self::MENSAJE_DE_ESTRUCTURA);
        }
        if (! $veredicto['asignable']) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::DESTINO_NO_ASIGNABLE, 'La posición destino ya no admite asignaciones.');
        }

        $ocupante = $this->reglasDeOcupacion->asignacionEnConflicto(
            $fechaEfectiva,
            $this->vinculos->asignacionesDePosicionDesde((int) $movimiento['posicion_destino_id'], $fechaEfectiva),
        );
        if ($ocupante !== null) {
            throw new IncidenciaDeAplicacion(IncidenciaDeAplicacion::DESTINO_OCUPADO, 'La posición destino está ocupada en el periodo del movimiento.');
        }
    }

    /** Registra la incidencia en una transacción corta aparte: persiste aunque la aplicación se haya revertido. */
    private function registrarIncidencia(ContextoDeTenant $contexto, ?int $actorId, Actuacion $previa, IncidenciaDeAplicacion $incidencia, ?string $clave, ?string $huella): void
    {
        try {
            $this->vinculos->ejecutarBajoBloqueo($contexto->tenantId, $previa->colaboradorId, function () use ($contexto, $actorId, $previa, $incidencia, $clave, $huella) {
                $actual = $this->actuaciones->porId($contexto->tenantId, $previa->id);
                if ($actual === null || ! $actual->estado->esPendienteDeAplicar()) {
                    return;
                }

                $this->actuaciones->cambiarEstado(
                    $actual,
                    EstadoDeActuacion::IncidenciaDeAplicacion,
                    $actual->estado === EstadoDeActuacion::IncidenciaDeAplicacion ? 'ReintentoDeAplicacion' : 'IncidenciaDeAplicacion',
                    $actorId,
                    mb_substr($incidencia->getMessage(), 0, 1000),
                    ['codigo' => $incidencia->codigo],
                    $clave,
                    $huella,
                    [],
                    [
                        'aplicacion_estado' => 'Incidencia',
                        'incidencia_codigo' => $incidencia->codigo,
                        'incidencia_detalle' => mb_substr($incidencia->getMessage(), 0, 300),
                        'intentos_aplicacion' => (int) ($actual->movimiento['intentos_aplicacion'] ?? 0) + 1,
                    ],
                );
            });
        } catch (SolicitudModificada|ConflictoDeUnicidad) {
            // Otra operación resolvió la actuación entre tanto: su resultado prevalece.
        }
    }

    /** Una repetición con incidencia vuelve a comunicar la incidencia; nunca la presenta como aplicada. */
    private function resultadoDeRepeticion(Actuacion $actuacion): ResultadoDeActuacion
    {
        if ($actuacion->estado === EstadoDeActuacion::IncidenciaDeAplicacion) {
            throw new IncidenciaDeAplicacion(
                (string) ($actuacion->movimiento['incidencia_codigo'] ?? IncidenciaDeAplicacion::INCONSISTENCIA),
                (string) ($actuacion->movimiento['incidencia_detalle'] ?? 'No se pudo aplicar el movimiento.'),
            );
        }

        return new ResultadoDeActuacion($actuacion, false);
    }
}
