<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;

/**
 * Puntaje de un candidato (FR-024/025): suma de peso × cumplimiento sobre las dimensiones evaluadas, con los pesos
 * reescalados para que sumen 100 %. Devuelve un porcentaje de 0 a 100 con un decimal, o `null` si no hay ninguna
 * dimensión evaluada.
 */
final class CalculadorDePuntaje
{
    /** @param  array<string, float|null>  $cumplimientos  dimensión => 0..1 o null */
    public function calcular(array $cumplimientos, PesosPorDimension $pesos): ?float
    {
        $presentes = [];
        foreach (DimensionDeAdecuacion::cases() as $d) {
            if (($cumplimientos[$d->value] ?? null) !== null) {
                $presentes[] = $d;
            }
        }

        if ($presentes === []) {
            return null;
        }

        $reescalados = $pesos->reescalar($presentes);
        $total = 0.0;
        foreach ($presentes as $d) {
            $total += $reescalados[$d->value] * (float) $cumplimientos[$d->value];
        }

        return round($total * 100, 1);
    }
}
