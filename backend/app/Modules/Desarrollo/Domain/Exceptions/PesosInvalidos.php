<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PesosInvalidos extends ErrorDeAdecuacion
{
    public static function sumaIncorrecta(int $suma): self
    {
        return new self("Los pesos deben sumar 100 (suman {$suma}).", 'pesos_invalidos', 'pesos');
    }

    public static function negativos(): self
    {
        return new self('Los pesos no pueden ser negativos.', 'pesos_invalidos', 'pesos');
    }
}
