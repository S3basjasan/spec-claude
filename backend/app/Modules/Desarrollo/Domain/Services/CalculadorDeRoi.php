<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Retorno de la inversión: (beneficio − costo) / costo × 100. Con costo cero no aplica (nulo). */
final class CalculadorDeRoi
{
    public function deAccion(float $beneficio, ?float $costo): ?float
    {
        if ($costo === null || $costo == 0.0) {
            return null;
        }

        return ($beneficio - $costo) / $costo * 100;
    }

    /**
     * @param  list<array{beneficio: float, costo: float}>  $acciones  acciones con evaluación de impacto
     */
    public function agregado(array $acciones): ?float
    {
        if ($acciones === []) {
            return null;
        }
        $beneficio = array_sum(array_column($acciones, 'beneficio'));
        $costo = array_sum(array_column($acciones, 'costo'));

        return $this->deAccion((float) $beneficio, (float) $costo);
    }
}
