<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class PoliticaNoConfigurada extends RuntimeException
{
    public function __construct(string $message = 'La política no está configurada para esta organización.')
    {
        parent::__construct($message);
    }
}
