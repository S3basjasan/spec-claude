<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ImpactoNoPermitido extends ErrorDeReporte
{
    public function __construct(string $mensaje = 'La evaluación de impacto solo se puede registrar para una acción finalizada.')
    {
        parent::__construct($mensaje, 'accion_no_finalizada', 'plan_accion_id');
    }
}
