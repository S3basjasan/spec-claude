<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class SinPermisoDeExportacion extends RuntimeException
{
    public function __construct(string $message = 'No tienes permiso para exportar esta ficha.')
    {
        parent::__construct($message);
    }
}
