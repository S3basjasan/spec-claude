<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class DepartamentoNoEncontrado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El departamento no existe.');
    }
}
