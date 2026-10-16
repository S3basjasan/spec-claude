<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class PlanNoDisponible extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Plan de Formación no disponible.');
    }
}
