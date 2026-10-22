<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;
use DateTimeImmutable;

/** Diagnóstico puntual e inmutable de un colaborador frente a un puesto objetivo. */
final class Adecuacion
{
    /** @param  list<ResultadoDeAdecuacion>  $resultados */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?CodigoDeAdecuacion $codigo,
        public readonly int $colaboradorId,
        public readonly ?int $puestoActualId,
        public readonly int $puestoObjetivoId,
        public readonly ?int $inscripcionId,
        public readonly ?int $nivelObjetivoId,
        public readonly ?float $experienciaRequeridaAnios,
        public readonly Preparacion $preparacion,
        public readonly int $umbralMeses,
        public readonly ?string $observaciones,
        public readonly int $creadoPor,
        public readonly ?DateTimeImmutable $creadoEn,
        public readonly array $resultados,
    ) {}

    /** @return list<ResultadoDeAdecuacion> */
    public function brechas(): array
    {
        return array_values(array_filter($this->resultados, fn (ResultadoDeAdecuacion $r) => $r->esBrecha()));
    }

    public function cantidadDeBrechas(): int
    {
        return count($this->brechas());
    }

    public function tiempoMaximoDeCierre(): ?int
    {
        $tiempos = array_filter(array_map(fn (ResultadoDeAdecuacion $r) => $r->tiempoCierreMeses, $this->brechas()), fn (?int $t) => $t !== null);

        return $tiempos === [] ? null : max($tiempos);
    }

    public function guardada(int $id, CodigoDeAdecuacion $codigo, DateTimeImmutable $creadoEn): self
    {
        return new self(
            $id, $this->tenantId, $codigo, $this->colaboradorId, $this->puestoActualId, $this->puestoObjetivoId,
            $this->inscripcionId, $this->nivelObjetivoId, $this->experienciaRequeridaAnios, $this->preparacion,
            $this->umbralMeses, $this->observaciones, $this->creadoPor, $creadoEn, $this->resultados,
        );
    }
}
