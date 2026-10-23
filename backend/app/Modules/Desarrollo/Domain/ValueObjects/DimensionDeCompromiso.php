<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Dimensión de un compromiso de mejora. */
enum DimensionDeCompromiso: string
{
    case Competencia = 'Competencia';
    case Conocimiento = 'Conocimiento';
    case Experiencia = 'Experiencia';
    case Meta = 'Meta';

    /** Competencia y Conocimiento llevan un elemento y niveles A–D (FR-010, FR-012). */
    public function llevaElemento(): bool
    {
        return $this === self::Competencia || $this === self::Conocimiento;
    }

    /** Experiencia y Meta llevan una descripción obligatoria (FR-011). */
    public function requiereDescripcion(): bool
    {
        return ! $this->llevaElemento();
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }
}
