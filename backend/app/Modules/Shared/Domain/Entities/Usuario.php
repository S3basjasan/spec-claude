<?php

namespace App\Modules\Shared\Domain\Entities;

use App\Modules\Shared\Domain\ValueObjects\Rol;

final class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $correo,
        public readonly Rol $rol,
        public readonly string $passwordHash,
    ) {}

    public function esAdministrador(): bool
    {
        return $this->rol === Rol::Admin;
    }
}
