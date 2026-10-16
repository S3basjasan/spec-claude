<?php

namespace App\Modules\Shared\Application\DTO;

use App\Modules\Shared\Domain\Entities\Usuario;

final class UsuarioDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $correo,
        public readonly string $rol,
        public readonly int $tenantId = 1,
        public readonly string $tenantNombre = '',
    ) {}

    public static function desde(Usuario $usuario): self
    {
        return new self($usuario->id, $usuario->nombre, $usuario->correo, $usuario->rol->value, $usuario->tenantId, $usuario->tenantNombre);
    }
}
