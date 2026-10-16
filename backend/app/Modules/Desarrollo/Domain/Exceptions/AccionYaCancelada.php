<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionYaCancelada extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('La acción ya está cancelada.', 'accion_ya_cancelada');
    }
}
