<?php

namespace App\Modules\Shared\Domain\Repositories;

use App\Modules\Shared\Domain\Entities\Usuario;

interface UsuarioRepositoryInterface
{
    public function buscarPorCorreo(string $correo): ?Usuario;

    public function obtenerPorId(int $id): ?Usuario;

    /**
     * Nombres de los usuarios existentes entre los ids dados (una sola consulta).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string> id => nombre
     */
    public function nombresPorIds(array $ids): array;
}
