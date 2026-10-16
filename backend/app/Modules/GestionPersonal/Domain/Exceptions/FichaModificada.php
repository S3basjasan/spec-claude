<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class FichaModificada extends RuntimeException
{
    public function __construct(string $message = 'La ficha cambió. Actualiza la información.')
    {
        parent::__construct($message);
    }
}
