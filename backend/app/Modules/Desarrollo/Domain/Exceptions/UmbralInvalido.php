<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class UmbralInvalido extends ErrorDeAdecuacion
{
    public function __construct()
    {
        parent::__construct('El umbral de preparación debe ser un número entero de meses mayor que cero.', 'umbral_invalido', 'umbral_meses');
    }
}
