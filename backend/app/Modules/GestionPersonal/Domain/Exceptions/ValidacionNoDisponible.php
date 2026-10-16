<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ValidacionNoDisponible extends RuntimeException
{
    public function __construct(string $message = 'No se pudo verificar el archivo. Inténtalo de nuevo.')
    {
        parent::__construct($message);
    }
}
