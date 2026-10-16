<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class CupoExcedido extends ErrorDePlan
{
    public function __construct(?string $campo = 'colaborador_ids')
    {
        parent::__construct('Los participantes superan la capacidad máxima de la acción.', 'cupo_excedido', $campo);
    }
}
