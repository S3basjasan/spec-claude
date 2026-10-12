<?php

namespace App\Modules\Shared\Application\UseCases;

use App\Modules\Shared\Application\DTO\UsuarioDTO;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;

final class ObtenerUsuarioActual
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios) {}

    public function ejecutar(int $usuarioId): ?UsuarioDTO
    {
        $usuario = $this->usuarios->obtenerPorId($usuarioId);

        return $usuario === null ? null : UsuarioDTO::desde($usuario);
    }
}
