<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class SinPermisoDeDocumentos extends RuntimeException
{
    public function __construct(string $message = 'No tienes permiso para consultar documentos.')
    {
        parent::__construct($message);
    }
}
