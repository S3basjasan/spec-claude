<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class CompetenciaYaTieneRegistro extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia ya tiene un registro de comportamientos.');
    }
}
