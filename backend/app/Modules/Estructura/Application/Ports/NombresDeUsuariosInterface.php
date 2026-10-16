<?php

namespace App\Modules\Estructura\Application\Ports;

/**
 * Puerto para obtener los nombres de los usuarios que crearon o modificaron un registro. Estructura no lee
 * la tabla de usuarios: el adaptador usa el contrato público del módulo Shared.
 */
interface NombresDeUsuariosInterface
{
    /**
     * @param  array<int, int>  $ids
     * @return array<int, string> id => nombre (omite los ids que no existen)
     */
    public function nombres(array $ids): array;

    /**
     * Ids de los usuarios cuyo nombre contiene el texto, sin distinguir mayúsculas ni tildes (máximo 200, por nombre).
     *
     * @return array<int, int>
     */
    public function idsPorNombre(string $texto): array;
}
