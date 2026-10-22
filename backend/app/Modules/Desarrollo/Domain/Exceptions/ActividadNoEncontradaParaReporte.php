<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ActividadNoEncontradaParaReporte extends ErrorDeReporte
{
    public function __construct(string $mensaje = 'La actividad no existe.')
    {
        parent::__construct($mensaje, 'actividad_no_encontrada');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
