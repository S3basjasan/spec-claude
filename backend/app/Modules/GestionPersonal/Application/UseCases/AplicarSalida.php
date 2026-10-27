<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Ports\CoordinadorDeCuentaPort;
use App\Modules\GestionPersonal\Application\Services\ControlDeOffboarding;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\IncidenciaDeSalida;
use App\Modules\GestionPersonal\Domain\Exceptions\SalidaNoAplicable;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Aplica una salida: cierra la asignación abierta y el vínculo en la fecha efectiva, de forma atómica y bajo el bloqueo
 * de la ficha. Lo ejecuta el sistema al llegar la fecha o el Administrador (manualmente o al reintentar una incidencia)
 * con la misma verificación. Si una condición actual lo impide no hay cambios parciales: se registra una incidencia en
 * una transacción aparte y nunca se comunica como aplicada. Después de confirmar, se solicita el tratamiento de la
 * cuenta; su resultado no revierte la salida y solo `Coordinada` se presenta como confirmado.
 */
final class AplicarSalida
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly ControlDeOffboarding $control,
        private readonly CoordinadorDeCuentaPort $cuentas,
    ) {}

    /**
     * @throws ExpedienteNoEncontrado
     * @throws ExpedienteModificado
     * @throws SalidaNoAplicable
     * @throws IncidenciaDeSalida La condición sigue sin cumplirse (la incidencia queda registrada).
     */
    public function comoAdministrador(ContextoDeTenant $contexto, int $userId, int $id, int $version, ?string $clave): ResultadoDeSalida
    {
        $clave = $this->control->normalizar($clave);
        $huella = $this->control->huella('aplicacion', $id, $version);

        $previa = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;

        $repetida = $this->control->repeticion($contexto->tenantId, $clave, $huella, $userId, $id);
        if ($repetida !== null) {
            return $this->resultadoDeRepeticion($repetida);
        }

        return $this->ejecutar($contexto, $userId, $previa, $version, $clave, $huella);
    }

    /** Aplicación automática con identidad de sistema. Idempotente: lo que ya no está pendiente se deja como está. */
    public function comoSistema(ContextoDeTenant $contexto, int $id): ResultadoDeSalida
    {
        $previa = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;

        try {
            return $this->ejecutar($contexto, null, $previa, null, null, null);
        } catch (IncidenciaDeSalida) {
            return new ResultadoDeSalida($this->expedientes->porId($contexto->tenantId, $id) ?? $previa, true);
        } catch (SalidaNoAplicable|ExpedienteModificado|ConflictoDeUnicidad) {
            return new ResultadoDeSalida($this->expedientes->porId($contexto->tenantId, $id) ?? $previa, false);
        }
    }

    private function ejecutar(ContextoDeTenant $contexto, ?int $actorId, Expediente $previa, ?int $version, ?string $clave, ?string $huella): ResultadoDeSalida
    {
        try {
            $resultado = $this->vinculos->ejecutarBajoBloqueo(
                $contexto->tenantId,
                $previa->colaboradorId,
                fn () => $this->aplicarBajoBloqueo($contexto, $actorId, $previa->id, $version, $clave, $huella),
            );
        } catch (IncidenciaDeSalida $incidencia) {
            $this->registrarIncidencia($contexto, $actorId, $previa, $incidencia, $clave, $huella);

            throw $incidencia;
        } catch (ConflictoDeUnicidad $conflicto) {
            if ($clave !== null && $huella !== null && $actorId !== null) {
                $repetida = $this->control->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
                if ($repetida !== null) {
                    return $this->resultadoDeRepeticion($repetida);
                }
            }

            throw $conflicto;
        }

        return $resultado->nueva && $resultado->expediente->estado === EstadoDeSalida::Efectiva
            ? new ResultadoDeSalida($this->coordinarCuenta($contexto, $resultado->expediente), true)
            : $resultado;
    }

    private function aplicarBajoBloqueo(ContextoDeTenant $contexto, ?int $actorId, int $id, ?int $version, ?string $clave, ?string $huella): ResultadoDeSalida
    {
        $actual = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;

        if ($version !== null && $actual->version !== $version) {
            throw new ExpedienteModificado;
        }
        if (! $actual->estado->esPendienteDeAplicar() || ($actorId === null && $actual->estado === EstadoDeSalida::IncidenciaDeAplicacion)) {
            throw new SalidaNoAplicable('La salida no está pendiente de aplicación.');
        }
        if ($actual->fechaEfectiva > $contexto->hoy()) {
            throw new SalidaNoAplicable('La fecha efectiva de la salida todavía no llegó.');
        }

        $this->vinculos->cerrarVinculoEnFecha($contexto->tenantId, $actual->vinculoId, $actual->fechaEfectiva, $actorId);

        $nuevo = $this->expedientes->actualizar($actual, [
            'estado' => 'Efectiva',
            'aplicada_en' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
            'aplicada_por' => $actorId,
            'aplicada_por_sistema' => $actorId === null ? 1 : 0,
            'incidencia_codigo' => null,
            'incidencia_detalle' => null,
            'intentos_aplicacion' => $actual->intentosAplicacion + 1,
        ], 'Aplicada', $actorId, ['reintento' => $actual->estado === EstadoDeSalida::IncidenciaDeAplicacion], $clave, $huella);

        return new ResultadoDeSalida($nuevo, true);
    }

    /** Solicita el tratamiento de la cuenta ya con la salida confirmada; un fallo no revierte nada ni se oculta. */
    private function coordinarCuenta(ContextoDeTenant $contexto, Expediente $expediente): Expediente
    {
        try {
            $estado = $this->cuentas->solicitar($contexto->tenantId, $expediente->colaboradorId, $expediente->fechaEfectiva);
        } catch (Throwable $error) {
            report($error);
            $estado = 'Incidencia';
        }

        if ($estado === $expediente->cuentaEstado) {
            return $expediente;
        }

        try {
            return $this->vinculos->ejecutarBajoBloqueo($contexto->tenantId, $expediente->colaboradorId, function () use ($contexto, $expediente, $estado) {
                $actual = $this->expedientes->porId($contexto->tenantId, $expediente->id) ?? $expediente;

                return $this->expedientes->actualizar($actual, ['cuenta_estado' => $estado], 'CuentaCoordinada', null, ['resultado' => $estado]);
            });
        } catch (Throwable $error) {
            report($error);

            return $this->expedientes->porId($contexto->tenantId, $expediente->id) ?? $expediente;
        }
    }

    /** Registra la incidencia en una transacción corta aparte: persiste aunque la aplicación se haya revertido. */
    private function registrarIncidencia(ContextoDeTenant $contexto, ?int $actorId, Expediente $previa, IncidenciaDeSalida $incidencia, ?string $clave, ?string $huella): void
    {
        try {
            $this->vinculos->ejecutarBajoBloqueo($contexto->tenantId, $previa->colaboradorId, function () use ($contexto, $actorId, $previa, $incidencia, $clave, $huella) {
                $actual = $this->expedientes->porId($contexto->tenantId, $previa->id);
                if ($actual === null || ! $actual->estado->esPendienteDeAplicar()) {
                    return;
                }

                $this->expedientes->actualizar($actual, [
                    'estado' => 'IncidenciaDeAplicacion',
                    'incidencia_codigo' => $incidencia->codigo,
                    'incidencia_detalle' => mb_substr($incidencia->getMessage(), 0, 300),
                    'intentos_aplicacion' => $actual->intentosAplicacion + 1,
                ], $actual->estado === EstadoDeSalida::IncidenciaDeAplicacion ? 'ReintentoDeAplicacion' : 'IncidenciaDeAplicacion', $actorId, ['codigo' => $incidencia->codigo], $clave, $huella);
            });
        } catch (ExpedienteModificado|ConflictoDeUnicidad) {
            // Otra operación resolvió el expediente entre tanto: su resultado prevalece.
        }
    }

    /** Una repetición con incidencia vuelve a comunicar la incidencia; nunca la presenta como aplicada. */
    private function resultadoDeRepeticion(Expediente $expediente): ResultadoDeSalida
    {
        if ($expediente->estado === EstadoDeSalida::IncidenciaDeAplicacion) {
            throw new IncidenciaDeSalida((string) ($expediente->incidenciaCodigo ?? IncidenciaDeSalida::INCONSISTENCIA), (string) ($expediente->incidenciaDetalle ?? 'No se pudo aplicar la salida.'));
        }

        return new ResultadoDeSalida($expediente, false);
    }
}
