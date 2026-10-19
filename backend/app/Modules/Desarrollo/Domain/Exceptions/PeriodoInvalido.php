<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PeriodoInvalido extends ErrorDeReporte
{
    public function __construct(string $mensaje = 'El rango de fechas no es válido: la fecha inicial no puede ser posterior a la final.')
    {
        parent::__construct($mensaje, 'periodo_invalido', 'fecha_desde');
    }
}
