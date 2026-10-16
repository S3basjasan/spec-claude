<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class MotivoRequerido extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('El motivo es obligatorio.', 'motivo_requerido', 'motivo');
    }
}
