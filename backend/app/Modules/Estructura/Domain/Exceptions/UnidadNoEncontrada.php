<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class UnidadNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La unidad no existe.');
    }
}
