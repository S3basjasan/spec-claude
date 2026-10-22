<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El quiz cambió o el intento ya no existe en curso: el cliente debe reiniciarlo. */
final class IntentoDescartado extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct('El quiz cambió o el intento terminó. Inicia el quiz de nuevo.', 'intento_descartado');
    }
}
