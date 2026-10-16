<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;

/** Resultado de una DNF para un colaborador: sus brechas por competencia. */
final class ResultadoColaborador
{
    /**
     * @param  list<Brecha>  $brechas
     */
    public function __construct(
        public readonly int $colaboradorId,
        public readonly int $puestoId,
        public readonly array $brechas,
    ) {}

    public function cantidadBrechas(): int
    {
        return count(array_filter($this->brechas, fn (Brecha $b) => $b->tieneBrecha()));
    }

    public function sinEvaluacion(): int
    {
        return count(array_filter($this->brechas, fn (Brecha $b) => $b->estado === EstadoBrecha::SinEvaluacion));
    }

    public function prioridadMasAlta(): ?PrioridadBrecha
    {
        $mas = null;
        foreach ($this->brechas as $brecha) {
            if ($brecha->prioridad !== null && ($mas === null || $brecha->prioridad->orden() > $mas->orden())) {
                $mas = $brecha->prioridad;
            }
        }

        return $mas;
    }
}
