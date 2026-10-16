<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class RegistroNoEncontrado extends RuntimeException
{
    public function __construct(string $message = 'El registro no existe.')
    {
        parent::__construct($message);
    }
}
