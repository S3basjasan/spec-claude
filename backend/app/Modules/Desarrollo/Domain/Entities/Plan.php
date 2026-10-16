<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\CodigoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use DateTimeImmutable;

/** Plan de formación de un año: agrupa las acciones formativas y sigue un ciclo de vida. */
final class Plan
{
    /**
     * @param  list<AccionFormativa>  $acciones
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?int $numero,
        public readonly string $nombre,
        public readonly string $nombreNormalizado,
        public readonly int $anio,
        public readonly ?string $descripcion,
        public readonly EstadoPlan $estado,
        public readonly ?int $aprobadorId,
        public readonly ?int $origenDnfId,
        public readonly ?DateTimeImmutable $fechaAprobacion,
        public readonly int $creadoPor,
        public readonly int $actualizadoPor,
        public readonly ?DateTimeImmutable $creadoEn,
        public readonly ?DateTimeImmutable $actualizadoEn,
        public readonly array $acciones,
    ) {}

    public function codigo(): ?string
    {
        return $this->numero === null ? null : CodigoPlan::desde($this->numero);
    }

    /** @return list<AccionFormativa> acciones no canceladas */
    public function accionesVigentes(): array
    {
        return array_values(array_filter($this->acciones, fn (AccionFormativa $a) => ! $a->cancelada));
    }

    public function cantidadAcciones(): int
    {
        return count($this->accionesVigentes());
    }

    public function costoTotal(): float
    {
        return round(array_sum(array_map(fn (AccionFormativa $a) => $a->costo ?? 0.0, $this->accionesVigentes())), 2);
    }

    public function horasTotales(): float
    {
        return round(array_sum(array_map(fn (AccionFormativa $a) => $a->duracionHoras ?? 0.0, $this->accionesVigentes())), 2);
    }

    public function accion(int $accionId): ?AccionFormativa
    {
        foreach ($this->acciones as $accion) {
            if ($accion->id === $accionId) {
                return $accion;
            }
        }

        return null;
    }
}
