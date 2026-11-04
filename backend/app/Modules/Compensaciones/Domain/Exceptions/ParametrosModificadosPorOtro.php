<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

final class ParametrosModificadosPorOtro extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales.');
    }
}
