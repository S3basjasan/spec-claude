<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class EscalaNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Selecciona la escala de los grados.');
    }
}
