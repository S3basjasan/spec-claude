<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class IngresosDeshabilitados extends RuntimeException
{
    public function __construct(string $message = 'El registro de ingresos está deshabilitado temporalmente.')
    {
        parent::__construct($message);
    }
}
