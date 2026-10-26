<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Falta el respaldo exigido, o no está accesible para quien presenta. */
final class DocumentoRequerido extends RuntimeException
{
    public function __construct(string $message = 'La política exige un documento de respaldo disponible.')
    {
        parent::__construct($message);
    }
}
