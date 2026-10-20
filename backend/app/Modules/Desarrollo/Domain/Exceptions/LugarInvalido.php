<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class LugarInvalido extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'El lugar o enlace no es válido.')
    {
        parent::__construct($mensaje, 'lugar_invalido', 'lugar_enlace');
    }
}
