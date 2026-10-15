<?php

namespace Tests\Unit\Estructura\Unidades;

use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;

/**
 * Doble del puerto de nombres de usuario.
 */
class NombresDeUsuariosEnMemoria implements NombresDeUsuariosInterface
{
    /** @param array<int, string> $nombres */
    public function __construct(public array $nombres = [1 => 'Ana', 2 => 'Luis']) {}

    public function nombres(array $ids): array
    {
        return array_intersect_key($this->nombres, array_flip($ids));
    }
}
