<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class InscripcionNoEncontrada extends ErrorDeRuta
{
    public function __construct(string $mensaje = 'La inscripción no existe.')
    {
        parent::__construct($mensaje, 'inscripcion_no_encontrada');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
