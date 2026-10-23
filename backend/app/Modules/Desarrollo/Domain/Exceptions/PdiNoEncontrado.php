<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El plan no existe o no es accesible para quien lo consulta. */
final class PdiNoEncontrado extends ErrorDePdi
{
    public function __construct()
    {
        parent::__construct('No se encontró el plan de desarrollo.', 'plan_no_encontrado');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
