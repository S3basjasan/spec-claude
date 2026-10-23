<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de un plan: Borrador, Acordado (doble confirmación) o Cerrado (solo lectura). */
enum EstadoDePdi: string
{
    case Borrador = 'Borrador';
    case Acordado = 'Acordado';
    case Cerrado = 'Cerrado';

    public function etiqueta(): string
    {
        return $this->value;
    }
}
