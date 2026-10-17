<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** La operación es solo del colaborador (con ficha asociada a su cuenta). */
final class ColaboradorSinAcceso extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct('Esta acción solo está disponible para colaboradores con ficha.', 'sin_acceso');
    }

    public function estadoHttp(): int
    {
        return 403;
    }
}
