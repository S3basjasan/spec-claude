<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class TransicionMicroInvalida extends ErrorDeMicro
{
    public function __construct(string $actual, string $nuevo)
    {
        parent::__construct(
            $actual === $nuevo
                ? "El micro-contenido ya está en estado $actual."
                : "No se puede pasar de $actual a $nuevo.",
            'transicion_invalida',
            'estado',
        );
    }
}
