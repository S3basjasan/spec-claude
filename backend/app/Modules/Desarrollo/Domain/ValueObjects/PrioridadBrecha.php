<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Prioridad de una brecha: ayuda visual del sistema para ordenar (no literal en la bibliografía). */
enum PrioridadBrecha: string
{
    case Critica = 'Critica';
    case Alta = 'Alta';
    case Media = 'Media';
    case Baja = 'Baja';

    /** Mayor valor = más prioritaria (Crítica 4 … Baja 1). */
    public function orden(): int
    {
        return match ($this) {
            self::Critica => 4,
            self::Alta => 3,
            self::Media => 2,
            self::Baja => 1,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Critica => 'Crítica',
            self::Alta => 'Alta',
            self::Media => 'Media',
            self::Baja => 'Baja',
        };
    }
}
