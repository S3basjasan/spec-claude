<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class FechasFueraDelAnio extends ErrorDePlan
{
    public function __construct(string $campo = 'fecha_inicio')
    {
        parent::__construct('Las fechas de la acción deben estar dentro del año del plan.', 'fechas_fuera_del_anio', $campo);
    }
}
