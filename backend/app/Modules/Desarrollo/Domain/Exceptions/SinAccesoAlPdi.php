<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El plan existe pero la operación no corresponde a quien la intenta (p. ej. aceptar un plan ajeno). */
final class SinAccesoAlPdi extends ErrorDePdi
{
    public function __construct()
    {
        parent::__construct('No tienes permisos para realizar esta acción sobre el plan.', 'sin_acceso');
    }

    public function estadoHttp(): int
    {
        return 403;
    }
}
