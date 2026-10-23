<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de una acción del plan. En acciones vinculadas se deriva del Plan de Formación (solo lectura). */
enum EstadoDeAccionPdi: string
{
    case Pendiente = 'Pendiente';
    case EnCurso = 'EnCurso';
    case Cumplida = 'Cumplida';
    case Cancelada = 'Cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            default => $this->value,
        };
    }

    /** Cumplida o Cancelada: una acción así ya no bloquea el cierre (FR-032). */
    public function esFinal(): bool
    {
        return $this === self::Cumplida || $this === self::Cancelada;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
