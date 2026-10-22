<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class PoliticaNoEncontrada extends RuntimeException
{
    public function __construct(string $message = 'La política solicitada no existe.')
    {
        parent::__construct($message);
    }
}
