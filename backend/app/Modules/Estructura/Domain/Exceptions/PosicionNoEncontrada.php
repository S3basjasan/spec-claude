<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class PosicionNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La posición no existe.');
    }
}
