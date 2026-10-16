<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class SinColaboradoresEvaluables extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hay colaboradores evaluables.');
    }
}
