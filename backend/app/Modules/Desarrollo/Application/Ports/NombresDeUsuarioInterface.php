<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Nombres de usuario para mostrar quien configuro y el historial (los ids no tienen clave foranea).
 */
interface NombresDeUsuarioInterface
{
    /**
     * @param  array<int, int>  $usuarioIds
     * @return array<int, string> id => nombre; los ids ausentes no aparecen
     */
    public function nombresDe(array $usuarioIds): array;
}
