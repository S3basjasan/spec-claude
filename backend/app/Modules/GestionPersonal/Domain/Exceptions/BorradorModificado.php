<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class BorradorModificado extends RuntimeException
{
    public function __construct(string $message = 'El borrador cambió o ya no está disponible. Recarga la versión actual.')
    {
        parent::__construct($message);
    }
}
