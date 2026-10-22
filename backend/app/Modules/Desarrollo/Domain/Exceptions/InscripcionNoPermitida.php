<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Operación sobre una inscripción que sus reglas no permiten, con el campo afectado cuando aplica. */
final class InscripcionNoPermitida extends ErrorDeRuta
{
    public function __construct(string $mensaje, string $codigo, ?string $campo = null)
    {
        parent::__construct($mensaje, $codigo, $campo);
    }
}
