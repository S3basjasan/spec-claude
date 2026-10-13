<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class PuestoModificado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El puesto cambió desde que lo abriste. Recarga para ver la versión actual.');
    }
}
