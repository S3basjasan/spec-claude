<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ParticipantesRepetidos extends ErrorDePlan
{
    public function __construct(?string $campo = 'colaborador_ids')
    {
        parent::__construct('Un colaborador no puede repetirse en la misma acción.', 'participantes_repetidos', $campo);
    }
}
