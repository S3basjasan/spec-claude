<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class RegistroNoEncontrado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El registro de comportamientos no existe.');
    }
}
