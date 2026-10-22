<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Puesto actual de los colaboradores (el de la posición de su asignación laboral vigente), que aporta Gestión de Personal. */
interface PuestoActualDelColaboradorInterface
{
    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, int|null> colaborador_id => puesto_id (nulo si no se pudo resolver)
     */
    public function puestoActualDe(int $tenantId, array $colaboradorIds, string $fecha): array;
}
