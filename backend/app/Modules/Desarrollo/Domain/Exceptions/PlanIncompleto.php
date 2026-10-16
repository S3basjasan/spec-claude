<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PlanIncompleto extends ErrorDePlan
{
    /**
     * @param  list<array{accion_id: int|null, campo: string}>  $faltantes
     */
    public function __construct(public readonly array $faltantes)
    {
        parent::__construct('El plan está incompleto: faltan datos para enviarlo a revisión.', 'plan_incompleto');
    }
}
