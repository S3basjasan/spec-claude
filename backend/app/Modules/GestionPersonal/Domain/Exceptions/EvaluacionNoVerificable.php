<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Aprobar un movimiento sin evaluación válida y verificable frente al puesto destino. */
final class EvaluacionNoVerificable extends RuntimeException
{
    public function __construct(string $message = 'No hay una evaluación verificable frente al puesto destino.')
    {
        parent::__construct($message);
    }
}
