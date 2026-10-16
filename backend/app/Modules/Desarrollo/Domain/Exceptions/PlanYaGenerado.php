<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class PlanYaGenerado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La DNF ya generó un plan.');
    }
}
