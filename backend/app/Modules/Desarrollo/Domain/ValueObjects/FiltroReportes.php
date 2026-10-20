<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Filtros comunes de los reportes: período, departamento actual del colaborador, tipo de formación y plan. */
final class FiltroReportes
{
    public const MICROLEARNING = 'Microlearning';

    public function __construct(
        public readonly PeriodoReporte $periodo,
        public readonly ?int $departamentoId = null,
        public readonly ?string $tipo = null,
        public readonly ?int $planId = null,
    ) {}

    /** @return list<string> los seis tipos de acción más Micro-learning */
    public static function tiposValidos(): array
    {
        return [...array_map(fn (TipoAccion $t) => $t->value, TipoAccion::cases()), self::MICROLEARNING];
    }

    public function esMicrolearning(): bool
    {
        return $this->tipo === self::MICROLEARNING;
    }

    public function conPeriodo(PeriodoReporte $periodo): self
    {
        return new self($periodo, $this->departamentoId, $this->tipo, $this->planId);
    }
}
