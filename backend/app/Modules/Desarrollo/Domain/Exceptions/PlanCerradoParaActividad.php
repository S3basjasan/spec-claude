<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PlanCerradoParaActividad extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'El plan está cerrado: la actividad y sus registros son de solo lectura.')
    {
        parent::__construct($mensaje, 'plan_cerrado', null);
    }
}
