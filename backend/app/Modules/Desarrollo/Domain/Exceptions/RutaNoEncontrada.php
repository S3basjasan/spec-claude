<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class RutaNoEncontrada extends ErrorDeRuta
{
    public function __construct(string $mensaje = 'La ruta no existe.')
    {
        parent::__construct($mensaje, 'ruta_no_encontrada');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
