<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El micro-contenido o el intento no existe o no es accesible para quien lo pide. */
final class MicroNoEncontrado extends ErrorDeMicro
{
    public function __construct(string $mensaje = 'El micro-contenido no existe.')
    {
        parent::__construct($mensaje, 'no_encontrado');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
