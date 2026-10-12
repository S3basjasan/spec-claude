<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class EstadoSinCambio extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia ya está en ese estado.');
    }
}
