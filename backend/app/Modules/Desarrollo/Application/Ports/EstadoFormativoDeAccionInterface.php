<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Estado de la actividad formativa y asistencia del colaborador en las acciones del Plan de Formación (solo lectura). */
interface EstadoFormativoDeAccionInterface
{
    /**
     * @param  list<int>  $accionIds
     * @return array<int, array{actividad_id: int, estado: string, asistencia: string}> accion_id => estado de su actividad y asistencia del colaborador
     */
    public function deColaborador(int $tenantId, int $colaboradorId, array $accionIds): array;
}
