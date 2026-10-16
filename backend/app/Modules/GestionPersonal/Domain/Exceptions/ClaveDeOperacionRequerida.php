<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ClaveDeOperacionRequerida extends RuntimeException
{
    public function __construct(string $message = 'Falta una clave de operación válida (Idempotency-Key).')
    {
        parent::__construct($message);
    }
}
