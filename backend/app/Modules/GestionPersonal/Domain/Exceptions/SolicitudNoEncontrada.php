<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Inexistente, ajena o de otro tenant: la respuesta es la misma en los tres casos. */
final class SolicitudNoEncontrada extends RuntimeException
{
    public function __construct(string $message = 'Solicitud no encontrada.')
    {
        parent::__construct($message);
    }
}
