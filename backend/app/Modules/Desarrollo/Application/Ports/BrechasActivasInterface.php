<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Brechas activas de un colaborador detectadas en la DNF (modulo aun no implementado).
 */
interface BrechasActivasInterface
{
    /**
     * @return array<int, array{competencia_id: int, nivel_requerido: string}>
     */
    public function brechasDe(int $usuarioId): array;
}
