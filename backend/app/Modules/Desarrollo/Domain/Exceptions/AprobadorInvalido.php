<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AprobadorInvalido extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('El aprobador debe ser un administrador activo.', 'aprobador_invalido', 'aprobador_id');
    }
}
