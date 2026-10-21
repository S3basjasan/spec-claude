<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class ConflictoDeVersion extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esta competencia cambió mientras la editabas. Recarga para ver la versión actual.');
    }
}
