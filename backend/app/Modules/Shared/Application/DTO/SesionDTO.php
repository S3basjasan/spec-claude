<?php

namespace App\Modules\Shared\Application\DTO;

final class SesionDTO
{
    public function __construct(
        public readonly string $token,
        public readonly UsuarioDTO $usuario,
    ) {}
}
