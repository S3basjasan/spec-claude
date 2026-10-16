<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class UsuarioNoEsAprobador extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('Solo el aprobador designado puede realizar esta acción.', 'usuario_no_es_aprobador');
    }

    public function estadoHttp(): int
    {
        return 403;
    }
}
