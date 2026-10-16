<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PlanCambioDeEstado extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('El plan cambió de estado mientras se procesaba la solicitud. Actualiza e inténtalo de nuevo.', 'plan_cambio_de_estado');
    }
}
