<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

final class NombreDeCategoriaRepetido extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe una categoría con ese nombre.');
    }
}
