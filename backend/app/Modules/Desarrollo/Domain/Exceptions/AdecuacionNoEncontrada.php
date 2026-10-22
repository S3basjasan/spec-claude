<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AdecuacionNoEncontrada extends ErrorDeAdecuacion
{
    public function __construct()
    {
        parent::__construct('La adecuación no existe.', 'adecuacion_no_encontrada');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
