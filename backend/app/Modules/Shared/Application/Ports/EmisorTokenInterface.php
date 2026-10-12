<?php

namespace App\Modules\Shared\Application\Ports;

use App\Modules\Shared\Domain\Entities\Usuario;

interface EmisorTokenInterface
{
    /** Emite un token sin expiración; la capacidad `admin` solo se concede al Administrador. */
    public function emitir(Usuario $usuario): string;

    /** Revoca únicamente el token con el que se hizo la petición actual. */
    public function revocarActual(int $usuarioId): void;
}
