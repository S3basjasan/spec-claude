<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class CompetenciaNoDisponible extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia no existe o no está activa.');
    }
}
