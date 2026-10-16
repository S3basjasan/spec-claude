<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class CompetenciaInactiva extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia está inactiva: no se pueden asociar recursos nuevos.');
    }
}
