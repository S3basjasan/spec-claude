<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class PreguntasNoEncontradas extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El registro de preguntas no existe.');
    }
}
