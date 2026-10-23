<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Falta el resultado de uno o más compromisos al cerrar el plan (FR-032). */
final class ResultadosIncompletos extends ErrorDePdi
{
    public function __construct()
    {
        parent::__construct('Indica el resultado de cada compromiso para cerrar el plan.', 'resultados_incompletos', 'resultados');
    }
}
