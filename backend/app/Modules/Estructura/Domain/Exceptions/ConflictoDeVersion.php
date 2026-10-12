<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class ConflictoDeVersion extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La competencia cambió desde que la abriste. Recarga para ver la versión actual.');
    }
}
