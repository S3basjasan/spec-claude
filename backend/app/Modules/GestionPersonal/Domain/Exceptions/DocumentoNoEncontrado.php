<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class DocumentoNoEncontrado extends RuntimeException
{
    public function __construct(string $message = 'El documento no existe.')
    {
        parent::__construct($message);
    }
}
