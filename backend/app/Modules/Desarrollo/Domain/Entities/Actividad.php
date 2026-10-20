<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\CodigoActividad;
use DateTimeImmutable;

/**
 * Actividad formativa: la ejecución de una única acción de un plan. Guarda solo lo propio (lugar o enlace y
 * departamento); los datos de la acción se leen del plan vigente y el estado se calcula.
 */
final class Actividad
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?int $numero,
        public readonly int $accionId,
        public readonly string $lugarEnlace,
        public readonly ?int $departamentoId,
        public readonly int $version,
        public readonly int $creadoPor,
        public readonly int $actualizadoPor,
        public readonly ?DateTimeImmutable $creadoEn = null,
        public readonly ?DateTimeImmutable $actualizadoEn = null,
    ) {}

    public function codigo(): ?string
    {
        return $this->numero === null ? null : CodigoActividad::desde($this->numero);
    }
}
