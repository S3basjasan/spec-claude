<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class RegistroModificado extends RuntimeException
{
    public function __construct(string $message = 'La información cambió. Actualiza los datos.')
    {
        parent::__construct($message);
    }
}
