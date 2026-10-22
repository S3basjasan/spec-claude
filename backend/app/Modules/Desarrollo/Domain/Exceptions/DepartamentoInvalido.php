<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class DepartamentoInvalido extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'El departamento no existe o no está activo.')
    {
        parent::__construct($mensaje, 'departamento_invalido', 'departamento_id');
    }
}
