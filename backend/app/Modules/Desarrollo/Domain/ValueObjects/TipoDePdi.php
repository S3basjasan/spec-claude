<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Tipo de plan de desarrollo individual. No cambia después de crear el plan. */
enum TipoDePdi: string
{
    case Mejora = 'Mejora';
    case Carrera = 'Carrera';
    case Talento = 'Talento';

    public function etiqueta(): string
    {
        return $this->value;
    }

    /** El puesto objetivo es obligatorio en Carrera y Talento (FR-004). */
    public function requierePuestoObjetivo(): bool
    {
        return $this !== self::Mejora;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
