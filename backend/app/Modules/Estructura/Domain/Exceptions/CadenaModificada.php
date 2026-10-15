<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class CadenaModificada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La cadena de tipos cambió desde que la abriste. Recarga para ver la versión actual.');
    }
}
