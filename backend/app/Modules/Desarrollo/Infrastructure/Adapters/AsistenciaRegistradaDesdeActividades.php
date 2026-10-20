<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AsistenciaRegistradaInterface;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;

/** Dice al Plan si un colaborador ya tiene asistencia registrada en la actividad de una acción. */
final class AsistenciaRegistradaDesdeActividades implements AsistenciaRegistradaInterface
{
    public function __construct(private readonly ActividadRepositoryInterface $actividades) {}

    public function tieneAsistencia(int $tenantId, int $accionId, int $colaboradorId): bool
    {
        $actividad = $this->actividades->obtenerPorAccion($tenantId, $accionId);

        return $actividad !== null && $this->actividades->tieneAsistencia((int) $actividad->id, $colaboradorId);
    }
}
