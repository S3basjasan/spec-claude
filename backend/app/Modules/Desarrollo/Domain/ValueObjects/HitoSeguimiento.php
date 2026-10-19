<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use DateTimeImmutable;

/** Hito del seguimiento posterior a una actividad: 30, 60 o 90 días corridos después de su fecha de fin. */
enum HitoSeguimiento: int
{
    case Treinta = 30;
    case Sesenta = 60;
    case Noventa = 90;

    public const VENTANA_DIAS = 15;

    public function vencimiento(DateTimeImmutable $fechaFin): DateTimeImmutable
    {
        return $fechaFin->setTime(0, 0)->modify('+'.$this->value.' days');
    }

    /** Último día en que se acepta la respuesta (inclusive). */
    public function limiteDeRespuesta(DateTimeImmutable $fechaFin): DateTimeImmutable
    {
        return $this->vencimiento($fechaFin)->modify('+'.self::VENTANA_DIAS.' days');
    }

    public function anterior(): ?self
    {
        return match ($this) {
            self::Treinta => null,
            self::Sesenta => self::Treinta,
            self::Noventa => self::Sesenta,
        };
    }
}
