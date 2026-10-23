<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\TransicionInvalidaDePdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;

/**
 * Registra a mano el estado de una acción sin vínculo en un plan Acordado (FR-021, FR-031). Las acciones vinculadas son
 * de solo lectura: su estado se deriva del Plan de Formación.
 */
final class RegistrarAvanceDeAccion
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /** @return array<string, mixed> el plan actualizado */
    public function ejecutar(int $tenantId, int $actorId, int $accionId, EstadoDeAccionPdi $estado): array
    {
        $encontrada = $this->repositorio->obtenerAccion($tenantId, $accionId) ?? throw new PdiNoEncontrado;
        $pdi = $this->repositorio->obtener($tenantId, $encontrada['pdi_id']) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaSeguimiento($pdi);

        $accion = $encontrada['accion'];
        if ($accion->estaVinculada()) {
            throw new TransicionInvalidaDePdi('El estado de una acción vinculada al Plan de Formación no se registra a mano.');
        }

        if ($accion->estado !== $estado) {
            $this->repositorio->transaccion(function () use ($accion, $estado, $pdi, $actorId, $accionId) {
                $this->repositorio->actualizarAccion($accionId, $estado, false, null, $actorId);
                $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Avance, $accionId, $accion->estado->value, $estado->value, $accion->descripcion, $actorId);
            });
        }

        return $this->armador->detalle($this->repositorio->obtener($tenantId, (int) $pdi->id), true, $actorId);
    }
}
