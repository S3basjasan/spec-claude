<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/** Periodo del plan: fecha de inicio y de fin (el fin no puede ser anterior al inicio). */
final class PeriodoDePdi
{
    public function __construct(public readonly DateTimeImmutable $inicio, public readonly DateTimeImmutable $fin)
    {
        if ($fin < $inicio) {
            throw new InvalidArgumentException('La fecha de fin no puede ser anterior a la de inicio.');
        }
    }

    public function contiene(DateTimeImmutable $fecha): bool
    {
        return $fecha->format('Y-m-d') >= $this->inicio->format('Y-m-d') && $fecha->format('Y-m-d') <= $this->fin->format('Y-m-d');
    }
}
