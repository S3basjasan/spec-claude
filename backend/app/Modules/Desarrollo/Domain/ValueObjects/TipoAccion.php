<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Modalidad de una acción formativa (Alles: capacitación, codesarrollo, mentoring, guías OJT). */
enum TipoAccion: string
{
    case Presencial = 'Presencial';
    case Virtual = 'Virtual';
    case ELearning = 'ELearning';
    case OJT = 'OJT';
    case Codesarrollo = 'Codesarrollo';
    case Mentoring = 'Mentoring';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ELearning => 'E-learning',
            default => $this->value,
        };
    }
}
