<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de un hito de seguimiento. */
enum EstadoDeHito: string
{
    case Pendiente = 'Pendiente';
    case Realizado = 'Realizado';

    public function etiqueta(): string
    {
        return $this->value;
    }
}
