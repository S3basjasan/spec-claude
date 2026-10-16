<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;

/**
 * Adaptador PROVISIONAL: Actividades Formativas aún no existe, así que no hay asistencias y el progreso del
 * colaborador será 0 %. Al implementarla se sustituye por uno que llame a su Facade y se cambia el binding en
 * DesarrolloServiceProvider.
 */
final class AsistenciaSinDatos implements AsistenciaInterface
{
    public function presentesPorAccion(array $accionIds, int $colaboradorId): array
    {
        return [];
    }
}
