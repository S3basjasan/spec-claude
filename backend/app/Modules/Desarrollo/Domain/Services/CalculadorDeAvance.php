<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;

/** Avance del plan: porcentaje de acciones Cumplidas sobre las acciones no Canceladas (FR-037). */
final class CalculadorDeAvance
{
    /**
     * @param  list<EstadoDeAccionPdi>  $estados  estado resuelto de cada acción
     * @return int|null porcentaje entero, o nulo si no hay acciones no Canceladas (no calculable)
     */
    public function calcular(array $estados): ?int
    {
        $vigentes = array_filter($estados, fn (EstadoDeAccionPdi $e) => $e !== EstadoDeAccionPdi::Cancelada);
        if ($vigentes === []) {
            return null;
        }

        $cumplidas = count(array_filter($vigentes, fn (EstadoDeAccionPdi $e) => $e === EstadoDeAccionPdi::Cumplida));

        return (int) round($cumplidas / count($vigentes) * 100);
    }
}
