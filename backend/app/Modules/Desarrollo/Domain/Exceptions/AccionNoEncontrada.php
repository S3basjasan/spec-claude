<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AccionNoEncontrada extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('La acción formativa no existe.', 'accion_no_encontrada');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
