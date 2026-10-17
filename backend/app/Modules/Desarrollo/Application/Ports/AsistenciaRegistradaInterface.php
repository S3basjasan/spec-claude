<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Consulta si un colaborador ya tiene asistencia registrada en la actividad de una acción (para el Plan). */
interface AsistenciaRegistradaInterface
{
    public function tieneAsistencia(int $tenantId, int $accionId, int $colaboradorId): bool;
}
