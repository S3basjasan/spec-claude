<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * No se puede desactivar la unidad: ella o alguna de sus dependientes activas tiene posiciones Ocupadas o Vacantes.
 */
final class DesactivacionBloqueada extends RuntimeException
{
    /**
     * @param  array<int, array{id: int, codigo: string, nombre: string, cantidad: int}>  $unidades
     */
    public function __construct(public readonly array $unidades)
    {
        $total = array_sum(array_column($unidades, 'cantidad'));
        $donde = implode(', ', array_map(fn (array $u) => "{$u['codigo']} {$u['nombre']} ({$u['cantidad']})", $unidades));

        parent::__construct(sprintf(
            'No se puede desactivar la unidad: hay %s en %s.',
            $total === 1 ? '1 posición ocupada o vacante' : "$total posiciones ocupadas o vacantes",
            $donde,
        ));
    }
}
