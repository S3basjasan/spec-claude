<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class QuizConIntentos extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct(
            'El quiz ya tiene intentos terminados: archívalo y crea uno nuevo para cambiar sus preguntas.',
            'quiz_con_intentos',
            'preguntas',
        );
    }
}
