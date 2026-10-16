<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PlanNoEncontrado extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('El plan no existe.', 'plan_no_encontrado');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
