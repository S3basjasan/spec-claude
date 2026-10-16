<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class AlmacenamientoNoDisponible extends RuntimeException
{
    public function __construct(string $message = 'El almacenamiento de documentos no está disponible. Inténtalo de nuevo.')
    {
        parent::__construct($message);
    }
}
