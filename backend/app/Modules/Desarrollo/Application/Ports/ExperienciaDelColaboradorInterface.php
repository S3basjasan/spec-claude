<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Años de experiencia acumulada de los colaboradores, que aporta Gestión de Personal. */
interface ExperienciaDelColaboradorInterface
{
    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, float|null> colaborador_id => años con un decimal (nulo si no hay dato)
     */
    public function aniosDe(int $tenantId, array $colaboradorIds): array;
}
