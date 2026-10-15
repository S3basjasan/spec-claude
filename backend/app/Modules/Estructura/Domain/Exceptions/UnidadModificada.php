<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class UnidadModificada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La unidad cambió desde que la abriste. Recarga para ver la versión actual.');
    }
}
