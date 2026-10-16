<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use DateTimeImmutable;
use InvalidArgumentException;

/** Diagnóstico de necesidades formativas: puntual e inmutable una vez generado. */
final class Dnf
{
    /**
     * @param  list<ResultadoColaborador>  $resultados
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?string $codigo,
        public readonly AlcanceDnf $alcance,
        public readonly ?int $departamentoId,
        public readonly int $competenciasOmitidas,
        public readonly ?int $planFormacionId,
        public readonly ?int $planGeneradoPor,
        public readonly ?DateTimeImmutable $planGeneradoEn,
        public readonly int $creadoPor,
        public readonly ?DateTimeImmutable $creadoEn,
        public readonly array $resultados,
    ) {
        if ($alcance === AlcanceDnf::Departamento && $departamentoId === null) {
            throw new InvalidArgumentException('Una DNF por departamento requiere el departamento.');
        }
        if ($alcance === AlcanceDnf::Individual && ($departamentoId !== null || count($resultados) > 1)) {
            throw new InvalidArgumentException('Una DNF individual no lleva departamento y evalúa a un solo colaborador.');
        }
    }

    public function tienePlan(): bool
    {
        return $this->planFormacionId !== null;
    }

    public function cantidadBrechas(): int
    {
        return array_sum(array_map(fn (ResultadoColaborador $r) => $r->cantidadBrechas(), $this->resultados));
    }

    public function sinEvaluacion(): int
    {
        return array_sum(array_map(fn (ResultadoColaborador $r) => $r->sinEvaluacion(), $this->resultados));
    }

    public function prioridadMasAlta(): ?PrioridadBrecha
    {
        $mas = null;
        foreach ($this->resultados as $resultado) {
            $p = $resultado->prioridadMasAlta();
            if ($p !== null && ($mas === null || $p->orden() > $mas->orden())) {
                $mas = $p;
            }
        }

        return $mas;
    }

    /** Copia con los datos asignados al guardar (identificador, código y fecha). */
    public function guardada(int $id, string $codigo, DateTimeImmutable $creadoEn): self
    {
        return new self(
            $id, $this->tenantId, $codigo, $this->alcance, $this->departamentoId, $this->competenciasOmitidas,
            $this->planFormacionId, $this->planGeneradoPor, $this->planGeneradoEn, $this->creadoPor, $creadoEn, $this->resultados,
        );
    }
}
