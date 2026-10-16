<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class EscrituraDeshabilitada extends RuntimeException
{
    public function __construct(string $message = 'El registro de fichas está deshabilitado temporalmente.')
    {
        parent::__construct($message);
    }
}
