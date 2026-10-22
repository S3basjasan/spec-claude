<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class FormatoDeExportacionInvalido extends ErrorDeReporte
{
    public function __construct(string $mensaje = 'El formato de exportación debe ser xlsx o pdf.')
    {
        parent::__construct($mensaje, 'formato_invalido', 'formato');
    }
}
