<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de un plan de formación (se guarda sin tilde; la interfaz muestra «Revisión»). */
enum EstadoPlan: string
{
    case Borrador = 'Borrador';
    case Revision = 'Revision';
    case Aprobado = 'Aprobado';
    case Ejecutando = 'Ejecutando';
    case Cerrado = 'Cerrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Revision => 'Revisión',
            default => $this->value,
        };
    }
}
