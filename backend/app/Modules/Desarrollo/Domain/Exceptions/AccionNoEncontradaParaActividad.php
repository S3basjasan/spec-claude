<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionNoEncontradaParaActividad extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'La acción del plan no existe.')
    {
        parent::__construct($mensaje, 'accion_no_encontrada', 'plan_accion_id');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
