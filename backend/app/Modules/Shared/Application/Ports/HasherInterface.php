<?php

namespace App\Modules\Shared\Application\Ports;

interface HasherInterface
{
    public function verificar(string $plano, string $hash): bool;

    /** Hash ficticio para igualar el tiempo de respuesta cuando el correo no existe. */
    public function hashFicticio(): string;
}
