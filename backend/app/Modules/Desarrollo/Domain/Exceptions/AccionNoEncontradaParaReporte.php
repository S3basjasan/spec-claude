<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionNoEncontradaParaReporte extends ErrorDeReporte
{
    public function __construct(string $mensaje = 'La acción no existe.')
    {
        parent::__construct($mensaje, 'accion_no_encontrada');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
