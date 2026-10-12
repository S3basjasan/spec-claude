<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class NombreDuplicado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe una competencia con ese nombre.');
    }
}
