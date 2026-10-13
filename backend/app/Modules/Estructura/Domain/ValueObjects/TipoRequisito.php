<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Los valores se guardan sin tildes; la interfaz y la auditoría usan la etiqueta con tilde.
 */
enum TipoRequisito: string
{
    case Formacion = 'Formacion';
    case Experiencia = 'Experiencia';
    case Capacitacion = 'Capacitacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Formacion => 'Formación',
            self::Experiencia => 'Experiencia',
            self::Capacitacion => 'Capacitación',
        };
    }

    /** Posición del tipo al agrupar los requisitos: Formación, Experiencia y Capacitación. */
    public function orden(): int
    {
        return match ($this) {
            self::Formacion => 1,
            self::Experiencia => 2,
            self::Capacitacion => 3,
        };
    }
}
