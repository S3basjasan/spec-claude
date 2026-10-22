<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class PoliticaNoDisponible extends RuntimeException
{
    public function __construct(string $message = 'No se pudo consultar la política. Inténtalo de nuevo.')
    {
        parent::__construct($message);
    }
}
