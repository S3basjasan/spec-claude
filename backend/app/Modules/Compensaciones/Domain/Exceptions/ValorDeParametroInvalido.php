<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

/** El valor de un cambio no cumple su regla; `indice` es la posición del cambio en la lista enviada. */
class ValorDeParametroInvalido extends RuntimeException
{
    public function __construct(public readonly int $indice, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
