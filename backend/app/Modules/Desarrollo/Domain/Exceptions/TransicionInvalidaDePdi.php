<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** La operación no está permitida en el estado actual del plan. */
class TransicionInvalidaDePdi extends ErrorDePdi
{
    public function __construct(string $mensaje = 'La operación no está permitida en el estado actual del plan.')
    {
        parent::__construct($mensaje, 'transicion_invalida');
    }
}
