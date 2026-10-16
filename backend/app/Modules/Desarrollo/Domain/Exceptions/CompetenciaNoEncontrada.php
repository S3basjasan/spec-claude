<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class CompetenciaNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia no existe.');
    }
}
