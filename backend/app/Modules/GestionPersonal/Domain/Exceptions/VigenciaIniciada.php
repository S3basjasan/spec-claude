<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class VigenciaIniciada extends RuntimeException
{
    public function __construct(string $message = 'La versión ya comenzó su vigencia y no puede retirarse: se sustituye publicando otra.')
    {
        parent::__construct($message);
    }
}
