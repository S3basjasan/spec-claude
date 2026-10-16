<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class DnfNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La DNF no existe.');
    }
}
