<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ActividadNoEncontrada extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'La actividad no existe.')
    {
        parent::__construct($mensaje, 'actividad_no_encontrada', null);
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
