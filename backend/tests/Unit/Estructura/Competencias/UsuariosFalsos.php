<?php

namespace Tests\Unit\Estructura\Competencias;

use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;

/** Doble del puerto de nombres de usuario: el usuario N se llama "Usuario N". */
final class UsuariosFalsos implements NombresDeUsuariosInterface
{
    public function nombres(array $ids): array
    {
        $nombres = [];
        foreach ($ids as $id) {
            $nombres[$id] = "Usuario $id";
        }

        return $nombres;
    }

    public function idsPorNombre(string $texto): array
    {
        return [];
    }
}
