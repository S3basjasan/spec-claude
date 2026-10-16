<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de una acción formativa. No se guarda: se calcula (salvo la cancelación, que es un dato). */
enum EstadoAccion: string
{
    case Programada = 'Programada';
    case EnCurso = 'EnCurso';
    case Finalizada = 'Finalizada';
    case Cancelada = 'Cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            default => $this->value,
        };
    }
}
