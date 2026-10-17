<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class TipoInmutable extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct('El tipo de un micro-contenido no se puede cambiar.', 'tipo_inmutable', 'tipo');
    }
}
