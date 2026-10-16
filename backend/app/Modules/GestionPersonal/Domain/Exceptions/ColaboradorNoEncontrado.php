<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ColaboradorNoEncontrado extends RuntimeException
{
    public function __construct(string $message = 'La ficha no existe.')
    {
        parent::__construct($message);
    }
}
