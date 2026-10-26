<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoResoluble;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\MaquinaDeEstados;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;

/**
 * Cambio de estado de una actuación existente: idempotente por clave, serializado por el bloqueo de la ficha y con
 * control optimista de la versión. La decisión (qué estado, con qué evidencia) la toma el caso de uso con la
 * actuación recién leída bajo el bloqueo; aquí se validan versión y transición, y se persiste todo o nada.
 */
final class TransicionadorDeActuacion
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly MaquinaDeEstados $maquina,
        private readonly ControlDeOperaciones $operaciones,
    ) {}

    /**
     * @param  Actuacion  $previa  Actuación ya autorizada para quien opera (el caso de uso comprobó su alcance).
     * @param  list<mixed>  $parteDeHuella  Contenido que distingue esta operación de otra con la misma clave.
     * @param  callable(Actuacion): array{nuevo: EstadoDeActuacion, evento: string, justificacion: string|null, detalle: array<string, mixed>, novedad: array<string, mixed>, movimiento: array<string, mixed>}  $decidir
     *
     * @throws SolicitudNoEncontrada
     * @throws SolicitudModificada
     * @throws EstadoNoResoluble
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, Actuacion $previa, int $version, string $accion, ?string $claveDeOperacion, array $parteDeHuella, callable $decidir): ResultadoDeActuacion
    {
        $clave = $this->operaciones->normalizar($claveDeOperacion);
        $huella = $this->operaciones->huella($accion, $previa->id, $version, $parteDeHuella);

        $repetida = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
        if ($repetida !== null) {
            return new ResultadoDeActuacion($repetida, false);
        }

        try {
            $resultado = $this->vinculos->ejecutarBajoBloqueo(
                $contexto->tenantId,
                $previa->colaboradorId,
                fn () => $this->confirmar($contexto, $actorId, $previa->id, $version, $clave, $huella, $decidir),
            );
        } catch (ConflictoDeUnicidad $conflicto) {
            $repetida = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
            if ($repetida !== null) {
                return new ResultadoDeActuacion($repetida, false);
            }

            throw $conflicto;
        }

        return $resultado;
    }

    /** @param callable(Actuacion): array<string, mixed> $decidir */
    private function confirmar(ContextoDeTenant $contexto, int $actorId, int $actuacionId, int $version, string $clave, string $huella, callable $decidir): ResultadoDeActuacion
    {
        $repetida = $this->operaciones->repeticion($contexto->tenantId, $clave, $huella, $actorId, $actuacionId);
        if ($repetida !== null) {
            return new ResultadoDeActuacion($repetida, false);
        }

        $actual = $this->actuaciones->porId($contexto->tenantId, $actuacionId) ?? throw new SolicitudNoEncontrada;
        if ($actual->version !== $version) {
            throw new SolicitudModificada;
        }

        $decision = $decidir($actual);
        if (! $this->maquina->puede($actual->tipo, $actual->estado, $decision['nuevo'])) {
            throw new EstadoNoResoluble;
        }

        $nueva = $this->actuaciones->cambiarEstado(
            $actual,
            $decision['nuevo'],
            $decision['evento'],
            $actorId,
            $decision['justificacion'],
            $decision['detalle'],
            $clave,
            $huella,
            $decision['novedad'],
            $decision['movimiento'],
        );

        return new ResultadoDeActuacion($nueva, true);
    }
}
