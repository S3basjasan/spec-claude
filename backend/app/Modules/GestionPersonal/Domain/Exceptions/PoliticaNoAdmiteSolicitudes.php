<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class PoliticaNoAdmiteSolicitudes extends RuntimeException
{
    public function __construct(string $message = 'La política no admite nuevas solicitudes.')
    {
        parent::__construct($message);
    }
}
