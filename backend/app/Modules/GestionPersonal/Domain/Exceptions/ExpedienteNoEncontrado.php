<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Inexistente, ajeno o de otro tenant: la respuesta es la misma. */
final class ExpedienteNoEncontrado extends RuntimeException
{
    public function __construct(string $message = 'Expediente no encontrado.')
    {
        parent::__construct($message);
    }
}
