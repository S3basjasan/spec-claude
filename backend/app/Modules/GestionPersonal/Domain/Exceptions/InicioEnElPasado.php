<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class InicioEnElPasado extends RuntimeException
{
    public function __construct(string $message = 'El inicio de vigencia debe ser posterior al momento actual.')
    {
        parent::__construct($message);
    }
}
