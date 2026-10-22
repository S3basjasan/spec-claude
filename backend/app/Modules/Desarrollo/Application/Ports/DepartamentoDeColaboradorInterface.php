<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Departamento actual de los colaboradores, que aporta Gestión de Personal. */
interface DepartamentoDeColaboradorInterface
{
    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, int|null> colaborador_id => unidad_id (nulo si no tiene asignación vigente)
     */
    public function actuales(int $tenantId, array $colaboradorIds, string $fecha): array;
}
