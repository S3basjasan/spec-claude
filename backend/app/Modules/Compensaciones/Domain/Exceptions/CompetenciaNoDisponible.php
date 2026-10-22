<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

final class CompetenciaNoDisponible extends RuntimeException
{
    public static function inexistente(): self
    {
        return new self('La competencia seleccionada no existe.');
    }

    public static function inactiva(): self
    {
        return new self('La competencia seleccionada está inactiva.');
    }
}
