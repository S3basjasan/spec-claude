<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class ConfiguracionModificada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La configuración cambió desde que la abriste. Recarga para ver la versión actual.');
    }
}
