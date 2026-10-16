<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use InvalidArgumentException;

/** Brecha de una competencia para un colaborador, tal como se calculó (inmutable). */
final class Brecha
{
    public function __construct(
        public readonly int $competenciaId,
        public readonly TipoCompetenciaDnf $tipo,
        public readonly NivelGrado $nivelRequerido,
        public readonly ?NivelGrado $nivelActual,
        public readonly ?int $diferencia,
        public readonly ?float $brechaPorcentaje,
        public readonly ?PrioridadBrecha $prioridad,
        public readonly EstadoBrecha $estado,
    ) {
        $this->validarInvariantes();
    }

    public function tieneBrecha(): bool
    {
        return $this->estado === EstadoBrecha::ConBrecha;
    }

    private function validarInvariantes(): void
    {
        if ($this->estado === EstadoBrecha::SinEvaluacion) {
            if ($this->nivelActual !== null || $this->diferencia !== null
                || $this->brechaPorcentaje !== null || $this->prioridad !== null) {
                throw new InvalidArgumentException('Una competencia sin evaluación no lleva nivel actual, diferencia, brecha ni prioridad.');
            }

            return;
        }

        if ($this->nivelActual === null || $this->diferencia === null || $this->brechaPorcentaje === null) {
            throw new InvalidArgumentException('Una competencia evaluada requiere nivel actual, diferencia y brecha.');
        }
        if (($this->prioridad !== null) !== ($this->estado === EstadoBrecha::ConBrecha)) {
            throw new InvalidArgumentException('La prioridad solo aplica a las competencias con brecha.');
        }

        $coherente = match ($this->estado) {
            EstadoBrecha::ConBrecha => $this->diferencia < 0 && $this->brechaPorcentaje > 0,
            EstadoBrecha::Cumple => $this->diferencia === 0 && $this->brechaPorcentaje === 0.0,
            EstadoBrecha::Supera => $this->diferencia > 0 && $this->brechaPorcentaje === 0.0,
        };
        if (! $coherente) {
            throw new InvalidArgumentException('El estado no coincide con la diferencia y la brecha.');
        }
    }
}
