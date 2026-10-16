<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Asistencia registrada de un colaborador. La gestiona Actividades Formativas (aún no implementada): el adaptador
 * actual es provisional y no devuelve asistencias. Este módulo nunca registra asistencia.
 */
interface AsistenciaInterface
{
    /**
     * Ids de las acciones, entre las dadas, en las que el colaborador tiene asistencia «Presente».
     *
     * @param  list<int>  $accionIds
     * @return list<int>
     */
    public function presentesPorAccion(array $accionIds, int $colaboradorId): array;
}
