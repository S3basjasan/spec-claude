<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use DateTimeImmutable;

/** Revisión intermedia que programa el responsable antes del cierre. */
final class HitoDePdi
{
    public function __construct(
        public readonly ?int $id,
        public readonly DateTimeImmutable $fecha,
        public readonly string $descripcion,
        public readonly EstadoDeHito $estado,
        public readonly ?string $observaciones,
    ) {}

    /** Un hito Pendiente con fecha anterior a hoy está vencido (FR-025). */
    public function estaVencido(DateTimeImmutable $hoy): bool
    {
        return $this->estado === EstadoDeHito::Pendiente && $this->fecha->format('Y-m-d') < $hoy->format('Y-m-d');
    }
}
