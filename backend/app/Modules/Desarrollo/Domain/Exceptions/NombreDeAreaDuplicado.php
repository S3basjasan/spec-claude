<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class NombreDeAreaDuplicado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe un área formativa con ese nombre.');
    }
}
