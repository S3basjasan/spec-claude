<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class RespuestaInvalida extends ErrorDeMicro
{
    public function __construct(string $mensaje, string $campo)
    {
        parent::__construct($mensaje, 'respuesta_invalida', $campo);
    }
}
