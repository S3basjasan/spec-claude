<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Promedio de calificaciones con un decimal (redondeo estándar, mitad hacia arriba). Sin calificaciones: nulo. */
final class CalculadorDePromedios
{
    public function promedio(int $suma, int $cantidad): ?float
    {
        if ($cantidad <= 0) {
            return null;
        }

        return round($suma / $cantidad, 1);
    }

    /** @param  list<int>  $calificaciones */
    public function promedioDe(array $calificaciones): ?float
    {
        return $this->promedio(array_sum($calificaciones), count($calificaciones));
    }
}
