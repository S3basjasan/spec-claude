<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ProgramadaExistente extends RuntimeException
{
    public function __construct(string $message = 'Ya existe una versión programada para este tipo. Retírala antes de publicar otra.')
    {
        parent::__construct($message);
    }
}
