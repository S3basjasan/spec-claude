<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** No se pudo obtener un dato necesario o generar el PDF: nunca se entrega un archivo parcial. */
final class ExportacionNoDisponible extends RuntimeException
{
    public function __construct(string $message = 'No se pudo generar la ficha. Inténtalo de nuevo.')
    {
        parent::__construct($message);
    }
}
