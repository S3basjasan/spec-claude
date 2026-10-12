<?php

namespace App\Modules\Shared\Application\DTO;

final class CredencialesDTO
{
    public function __construct(
        public readonly string $correo,
        public readonly string $password,
    ) {}
}
