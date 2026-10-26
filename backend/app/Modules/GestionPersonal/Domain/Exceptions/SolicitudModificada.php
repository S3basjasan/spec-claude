<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Versión obsoleta o transición ya realizada por otra operación. */
final class SolicitudModificada extends RuntimeException
{
    public function __construct(string $message = 'La solicitud cambió. Actualiza los datos antes de continuar.')
    {
        parent::__construct($message);
    }
}
