<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class EscalaSinCambio extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia ya usa esa escala.');
    }
}
