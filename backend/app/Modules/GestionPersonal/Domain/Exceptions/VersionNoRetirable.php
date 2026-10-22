<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class VersionNoRetirable extends RuntimeException
{
    public function __construct(string $message = 'Solo puede retirarse una versión programada que aún no comenzó.')
    {
        parent::__construct($message);
    }
}
