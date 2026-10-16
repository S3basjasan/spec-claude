<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class CompetenciaInvalidaEnPlan extends ErrorDePlan
{
    public function __construct(?string $campo = 'competencia_ids')
    {
        parent::__construct('Alguna competencia no existe o está inactiva.', 'competencia_invalida', $campo);
    }
}
