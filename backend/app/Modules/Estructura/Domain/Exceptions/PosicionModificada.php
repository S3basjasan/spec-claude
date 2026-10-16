<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class PosicionModificada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La posición cambió desde que la abriste. Recarga para ver la versión actual.');
    }
}
