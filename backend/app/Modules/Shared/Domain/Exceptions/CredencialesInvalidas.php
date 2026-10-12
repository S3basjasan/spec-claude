<?php

namespace App\Modules\Shared\Domain\Exceptions;

use RuntimeException;

final class CredencialesInvalidas extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Correo o contraseña incorrectos.');
    }
}
