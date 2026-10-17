<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class MicroYaCompletado extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct('Este micro-contenido ya está completado.', 'ya_completado');
    }
}
