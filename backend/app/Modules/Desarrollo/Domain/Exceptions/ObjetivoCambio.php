<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ObjetivoCambio extends ErrorDeAdecuacion
{
    public function __construct()
    {
        parent::__construct('El puesto objetivo cambió mientras generabas la adecuación. Vuelve a calcular el diagnóstico.', 'objetivo_cambio');
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
