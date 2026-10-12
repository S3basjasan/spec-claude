<?php

namespace App\Modules\Shared\Domain\Repositories;

use App\Modules\Shared\Domain\Entities\Usuario;

interface UsuarioRepositoryInterface
{
    public function buscarPorCorreo(string $correo): ?Usuario;

    public function obtenerPorId(int $id): ?Usuario;
}
