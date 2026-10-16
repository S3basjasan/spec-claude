<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class DocumentoModificado extends RuntimeException
{
    public function __construct(string $message = 'El documento cambió. Actualiza la información.')
    {
        parent::__construct($message);
    }
}
