<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class AreaNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El área formativa no existe.');
    }
}
