<?php

namespace App\Modules\Compensaciones\Application\Ports;

/** Nombres de usuario obtenidos de Shared por su contrato público. */
interface NombresDeUsuarioPort
{
    /**
     * @param  array<int, int>  $usuarioIds
     * @return array<int, string> id => nombre (solo los usuarios que existen)
     */
    public function nombresDe(array $usuarioIds): array;
}
