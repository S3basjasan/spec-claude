<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class CodigoDePuestoNoEditable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El código de un puesto no se puede cambiar.');
    }
}
