<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Las preguntas del quiz no cumplen las reglas; cada error se asocia a su posición, por ejemplo `preguntas.0.opciones`. */
final class QuizInvalido extends ErrorDeMicro
{
    /**
     * @param  array<string, string>  $errores  Clave de error → mensaje.
     */
    public function __construct(private readonly array $errores)
    {
        parent::__construct(reset($errores) ?: 'El quiz no es válido.', 'quiz_invalido');
    }

    /**
     * @return array<string, string>
     */
    public function errores(): array
    {
        return $this->errores;
    }
}
