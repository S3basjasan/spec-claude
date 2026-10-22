<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use InvalidArgumentException;

/** Resultado de un requisito evaluado de una adecuación, tal como se calculó (inmutable). */
final class ResultadoDeAdecuacion
{
    public function __construct(
        public readonly DimensionDeAdecuacion $dimension,
        public readonly ?string $tipoElemento,
        public readonly ?int $elementoId,
        public readonly ?string $nombreElemento,
        public readonly ?NivelGrado $requeridoActualNivel,
        public readonly ?NivelGrado $requeridoObjetivoNivel,
        public readonly ?NivelGrado $valorNivel,
        public readonly ?float $requeridoActualAnios,
        public readonly ?float $requeridoObjetivoAnios,
        public readonly ?float $valorAnios,
        public readonly ?float $diferencia,
        public readonly EstadoDeResultado $estado,
        public readonly ?int $tiempoCierreMeses = null,
    ) {
        $this->validarInvariantes();
    }

    /** Clave estable del requisito dentro de una adecuación: `Competencia:12` o `Experiencia:0`. */
    public function clave(): string
    {
        return $this->dimension->value.':'.($this->elementoId ?? 0);
    }

    public function esBrecha(): bool
    {
        return $this->estado === EstadoDeResultado::ConBrecha;
    }

    public function conTiempoDeCierre(?int $meses): self
    {
        return new self(
            $this->dimension, $this->tipoElemento, $this->elementoId, $this->nombreElemento,
            $this->requeridoActualNivel, $this->requeridoObjetivoNivel, $this->valorNivel,
            $this->requeridoActualAnios, $this->requeridoObjetivoAnios, $this->valorAnios,
            $this->diferencia, $this->estado, $meses,
        );
    }

    /** Cumplimiento frente al puesto objetivo: `min(1, valor ÷ requerido)`; nulo sin valor de la persona. */
    public function cumplimientoObjetivo(): ?float
    {
        return $this->cumplimiento($this->requeridoObjetivoNivel, $this->requeridoObjetivoAnios);
    }

    /** Cumplimiento frente al puesto actual (informativo); nulo si el puesto actual no exige el requisito. */
    public function cumplimientoActual(): ?float
    {
        return $this->cumplimiento($this->requeridoActualNivel, $this->requeridoActualAnios);
    }

    private function cumplimiento(?NivelGrado $requeridoNivel, ?float $requeridoAnios): ?float
    {
        if ($this->estado === EstadoDeResultado::SinEvaluacion) {
            return null;
        }

        if ($this->dimension->llevaGrado()) {
            if ($requeridoNivel === null || $this->valorNivel === null) {
                return null;
            }

            return min(1.0, $this->valorNivel->valorNumerico() / $requeridoNivel->valorNumerico());
        }

        if ($requeridoAnios === null || $this->valorAnios === null) {
            return null;
        }

        return $requeridoAnios <= 0 ? 1.0 : min(1.0, $this->valorAnios / $requeridoAnios);
    }

    private function validarInvariantes(): void
    {
        if ($this->dimension->llevaGrado() && ($this->tipoElemento === null || $this->elementoId === null)) {
            throw new InvalidArgumentException('Una competencia o un conocimiento lleva elemento.');
        }
        if (! $this->dimension->llevaGrado() && ($this->tipoElemento !== null || $this->elementoId !== null)) {
            throw new InvalidArgumentException('La experiencia no lleva elemento.');
        }

        match ($this->estado) {
            EstadoDeResultado::SinEvaluacion => $this->exigir($this->diferencia === null && $this->tiempoCierreMeses === null, 'Un requisito sin evaluación no lleva diferencia ni tiempo de cierre.'),
            EstadoDeResultado::ConBrecha => $this->exigir($this->diferencia !== null && $this->diferencia < 0, 'Una brecha tiene diferencia negativa.'),
            EstadoDeResultado::Cumple => $this->exigir($this->diferencia !== null && $this->diferencia == 0.0 && $this->tiempoCierreMeses === null, 'Cumplir implica diferencia cero y sin tiempo de cierre.'),
            EstadoDeResultado::Supera => $this->exigir($this->diferencia !== null && $this->diferencia > 0 && $this->tiempoCierreMeses === null, 'Superar implica diferencia positiva y sin tiempo de cierre.'),
        };

        if ($this->tiempoCierreMeses !== null && $this->tiempoCierreMeses < 1) {
            throw new InvalidArgumentException('El tiempo de cierre debe ser de al menos un mes.');
        }
    }

    private function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw new InvalidArgumentException($mensaje);
        }
    }
}
