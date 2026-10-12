<?php

namespace App\Modules\Shared\Application\UseCases;

use App\Modules\Shared\Application\Ports\EmisorTokenInterface;

final class CerrarSesion
{
    public function __construct(private readonly EmisorTokenInterface $emisor) {}

    public function ejecutar(int $usuarioId): void
    {
        $this->emisor->revocarActual($usuarioId);
    }
}
