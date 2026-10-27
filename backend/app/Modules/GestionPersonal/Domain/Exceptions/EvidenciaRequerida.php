<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Una tarea con evidencia obligatoria sin respaldo válido. */
final class EvidenciaRequerida extends RuntimeException
{
    public function __construct(string $message = 'La tarea exige al menos un respaldo disponible y accesible.')
    {
        parent::__construct($message);
    }
}
