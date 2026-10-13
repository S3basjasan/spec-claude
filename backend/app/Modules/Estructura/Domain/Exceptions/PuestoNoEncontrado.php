<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class PuestoNoEncontrado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El puesto no existe.');
    }
}
