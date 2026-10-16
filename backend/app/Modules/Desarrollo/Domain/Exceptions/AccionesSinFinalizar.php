<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionesSinFinalizar extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('Todas las acciones deben estar finalizadas o canceladas para cerrar el plan.', 'acciones_sin_finalizar');
    }
}
