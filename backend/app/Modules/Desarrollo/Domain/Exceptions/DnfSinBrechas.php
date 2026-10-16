<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class DnfSinBrechas extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La DNF no tiene brechas.');
    }
}
