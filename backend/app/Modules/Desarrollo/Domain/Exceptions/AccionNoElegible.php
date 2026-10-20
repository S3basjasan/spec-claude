<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionNoElegible extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'La acción no admite una actividad: debe estar activa, pertenecer a un plan en ejecución y no tener ya una actividad.')
    {
        parent::__construct($mensaje, 'accion_no_elegible', 'plan_accion_id');
    }
}
