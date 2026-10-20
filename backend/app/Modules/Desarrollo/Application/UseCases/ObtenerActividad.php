<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Exceptions\ActividadNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;

/** Detalle de una actividad con los datos vigentes de su acción y sus participantes. */
final class ObtenerActividad
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ActividadNoEncontrada
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $id): array
    {
        $actividad = $this->actividades->obtener($tenantId, $id) ?? throw new ActividadNoEncontrada;
        $accion = $this->acciones->obtener($tenantId, $actividad->accionId) ?? throw new ActividadNoEncontrada;

        return $this->armador->detalle($tenantId, $usuarioId, $actividad, $accion);
    }
}
