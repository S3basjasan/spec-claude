<?php

namespace App\Modules\Shared\Domain\Repositories;

use App\Modules\Shared\Domain\Entities\Usuario;

interface UsuarioRepositoryInterface
{
    public function buscarPorCorreo(string $correo): ?Usuario;

    public function obtenerPorId(int $id): ?Usuario;

    /**
     * Nombres de los usuarios existentes entre los ids dados (solo lectura; sin correo ni rol).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string> id => nombre
     */
    public function nombresPorIds(array $ids): array;

    /**
     * Ids de los usuarios cuyo nombre contiene el texto, sin distinguir mayúsculas ni tildes, ordenados por nombre y
     * limitados a `$limite` (solo lectura).
     *
     * @return array<int, int>
     */
    public function idsPorNombre(string $texto, int $limite): array;
}
