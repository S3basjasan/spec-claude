<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * No se puede desactivar la unidad: ella o alguna de sus dependientes activas tiene posiciones Ocupadas o Vacantes
 * (o, desde GP-02, posiciones con una asignación laboral vigente o futura).
 */
final class DesactivacionBloqueada extends RuntimeException
{
    /**
     * @param  array<int, array{id: int, codigo: string, nombre: string, cantidad: int}>  $unidades
     */
    public function __construct(public readonly array $unidades, bool $conAsignaciones = false)
    {
        $total = array_sum(array_column($unidades, 'cantidad'));
        $donde = implode(', ', array_map(fn (array $u) => "{$u['codigo']} {$u['nombre']} ({$u['cantidad']})", $unidades));

        if ($conAsignaciones) {
            $que = $total === 1 ? '1 posición ocupada, vacante o con asignación vigente' : "$total posiciones ocupadas, vacantes o con asignaciones vigentes";
        } else {
            $que = $total === 1 ? '1 posición ocupada o vacante' : "$total posiciones ocupadas o vacantes";
        }

        parent::__construct(sprintf('No se puede desactivar la unidad: hay %s en %s.', $que, $donde));
    }
}
