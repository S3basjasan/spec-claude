<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

enum TipoFuncion: string
{
    case Esencial = 'Esencial';
    case Complementaria = 'Complementaria';

    /** Texto con el que se muestra y se audita el tipo. */
    public function etiqueta(): string
    {
        return $this->value;
    }
}
