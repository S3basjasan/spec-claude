<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Creación del borrador de Plan de Formación a partir de una DNF. El módulo Plan de Formación aún no existe:
 * el adaptador actual es provisional.
 */
interface PlanDeFormacionInterface
{
    /**
     * @param  list<array{colaborador_id: int, competencia_id: int, nivel_requerido: string, nivel_actual: string|null, diferencia: int|null, prioridad: string|null}>  $brechas
     * @return int identificador del borrador creado
     */
    public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int;
}
