<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Dimensión que se compara entre el puesto y la persona. */
enum DimensionDeAdecuacion: string
{
    case Competencia = 'Competencia';
    case Conocimiento = 'Conocimiento';
    case Experiencia = 'Experiencia';

    /** Las dimensiones de grado (A–D) llevan elemento; Experiencia se mide en años. */
    public function llevaGrado(): bool
    {
        return $this !== self::Experiencia;
    }
}
