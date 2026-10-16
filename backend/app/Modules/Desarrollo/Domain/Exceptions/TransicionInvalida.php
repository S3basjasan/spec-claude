<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class TransicionInvalida extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('La transición de estado no está permitida.', 'transicion_invalida', 'estado');
    }
}
