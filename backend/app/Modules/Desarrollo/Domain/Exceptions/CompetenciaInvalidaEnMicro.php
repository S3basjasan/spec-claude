<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Una competencia nueva no existe o está inactiva; las ya vinculadas se conservan. */
final class CompetenciaInvalidaEnMicro extends ErrorDeMicro
{
    public function __construct(string $mensaje = 'Solo se pueden asociar competencias activas.')
    {
        parent::__construct($mensaje, 'competencia_inactiva', 'competencia_ids');
    }
}
