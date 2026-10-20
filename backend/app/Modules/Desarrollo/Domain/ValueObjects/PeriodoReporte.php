<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use App\Modules\Desarrollo\Domain\Exceptions\PeriodoInvalido;
use DateTimeImmutable;

/** Rango de fechas inclusivo de un reporte. Su período anterior equivalente tiene igual duración y termina el día previo. */
final class PeriodoReporte
{
    public readonly DateTimeImmutable $desde;

    public readonly DateTimeImmutable $hasta;

    public function __construct(DateTimeImmutable $desde, DateTimeImmutable $hasta)
    {
        $this->desde = $desde->setTime(0, 0);
        $this->hasta = $hasta->setTime(0, 0);
        if ($this->desde > $this->hasta) {
            throw new PeriodoInvalido;
        }
    }

    public static function anioDe(DateTimeImmutable $hoy): self
    {
        $anio = $hoy->format('Y');

        return new self(new DateTimeImmutable($anio.'-01-01'), new DateTimeImmutable($anio.'-12-31'));
    }

    /** Cantidad de días del rango, contando ambos extremos. */
    public function dias(): int
    {
        return (int) $this->desde->diff($this->hasta)->days + 1;
    }

    public function anterior(): self
    {
        $dias = $this->dias();
        $hastaAnterior = $this->desde->modify('-1 day');

        return new self($hastaAnterior->modify('-'.($dias - 1).' days'), $hastaAnterior);
    }

    public function contiene(DateTimeImmutable $fecha): bool
    {
        $dia = $fecha->setTime(0, 0);

        return $dia >= $this->desde && $dia <= $this->hasta;
    }

    /** @return array{desde: string, hasta: string} */
    public function aArreglo(): array
    {
        return ['desde' => $this->desde->format('Y-m-d'), 'hasta' => $this->hasta->format('Y-m-d')];
    }
}
