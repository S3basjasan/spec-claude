<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class InformacionNoDisponible extends RuntimeException
{
    public function __construct(string $message = 'La información no está disponible. Intenta de nuevo.')
    {
        parent::__construct($message);
    }
}
