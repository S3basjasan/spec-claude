<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Nivel actual de un colaborador en cada competencia. Lo aportara Gestion de Personal (modulo aun no implementado).
 */
interface NivelesDeColaboradorInterface
{
    /**
     * @return array<int, string> competencia_id => 'A'|'B'|'C'|'D' (solo las competencias con nivel registrado)
     */
    public function nivelesDe(int $usuarioId): array;
}
