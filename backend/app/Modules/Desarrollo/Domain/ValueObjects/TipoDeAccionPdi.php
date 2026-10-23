<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Tipo de una acción del plan. Solo Curso, Mentoring y Codesarrollo pueden vincularse al Plan de Formación (FR-018). */
enum TipoDeAccionPdi: string
{
    case Curso = 'Curso';
    case Mentoring = 'Mentoring';
    case Coaching = 'Coaching';
    case Autodesarrollo = 'Autodesarrollo';
    case Codesarrollo = 'Codesarrollo';
    case NuevaResponsabilidad = 'NuevaResponsabilidad';
    case Otra = 'Otra';

    public function esVinculable(): bool
    {
        return $this === self::Curso || $this === self::Mentoring || $this === self::Codesarrollo;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NuevaResponsabilidad => 'Nueva responsabilidad',
            default => $this->value,
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
