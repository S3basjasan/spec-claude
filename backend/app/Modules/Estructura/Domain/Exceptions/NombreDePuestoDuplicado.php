<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class NombreDePuestoDuplicado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe un puesto con ese nombre.');
    }
}
