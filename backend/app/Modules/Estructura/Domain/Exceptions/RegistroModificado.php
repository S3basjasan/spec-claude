<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class RegistroModificado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El registro cambió desde que lo abriste. Recarga para ver la versión actual.');
    }
}
