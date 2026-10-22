<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class SinRequisitosEvaluables extends ErrorDeAdecuacion
{
    public function __construct()
    {
        parent::__construct('El puesto objetivo no tiene requisitos que evaluar.', 'sin_requisitos_evaluables', 'puesto_objetivo_id');
    }
}
