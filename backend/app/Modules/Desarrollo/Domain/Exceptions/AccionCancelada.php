<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionCancelada extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('La acción está cancelada y no admite cambios de participantes.', 'accion_cancelada');
    }
}
