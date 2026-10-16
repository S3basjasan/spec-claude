<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class SinPermisoDeFicha extends RuntimeException
{
    public function __construct(string $message = 'No tienes permiso para consultar tu ficha.')
    {
        parent::__construct($message);
    }
}
