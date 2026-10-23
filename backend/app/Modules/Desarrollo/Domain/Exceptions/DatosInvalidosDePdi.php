<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Uno o varios datos del plan no cumplen las reglas de negocio (errores por campo). */
final class DatosInvalidosDePdi extends ErrorDePdi
{
    /**
     * @param  array<string, list<string>>  $errores
     */
    public static function conErrores(array $errores): self
    {
        return new self('Revisa los datos del plan.', 'datos_invalidos', null, $errores);
    }

    public static function campo(string $campo, string $mensaje): self
    {
        return new self($mensaje, 'datos_invalidos', $campo);
    }
}
