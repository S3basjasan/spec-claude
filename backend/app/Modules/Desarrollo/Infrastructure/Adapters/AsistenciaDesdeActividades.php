<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;

/**
 * Asistencia real tomada de Actividades Formativas. Devuelve las acciones en las que el colaborador tiene asistencia
 * Presente; el Plan aplica además que la acción esté Finalizada para contarla como completada.
 */
final class AsistenciaDesdeActividades implements AsistenciaInterface
{
    public function __construct(private readonly ActividadRepositoryInterface $actividades) {}

    public function presentesPorAccion(array $accionIds, int $colaboradorId): array
    {
        return $this->actividades->accionesConPresente($accionIds, $colaboradorId);
    }
}
