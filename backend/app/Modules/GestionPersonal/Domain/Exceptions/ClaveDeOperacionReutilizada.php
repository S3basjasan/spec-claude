<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ClaveDeOperacionReutilizada extends RuntimeException
{
    public function __construct(string $message = 'La clave de operación ya se usó con otros datos.')
    {
        parent::__construct($message);
    }
}
