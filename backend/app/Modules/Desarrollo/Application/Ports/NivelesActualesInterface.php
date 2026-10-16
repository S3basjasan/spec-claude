<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Nivel actual de cada colaborador por competencia. Lo aportará Gestión de Personal (GP-02, aún no implementada).
 */
interface NivelesActualesInterface
{
    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, array<int, string>> colaborador_id => [competencia_id => 'A'|'B'|'C'|'D'] (solo con nivel registrado)
     */
    public function nivelesDe(int $tenantId, array $colaboradorIds): array;
}
