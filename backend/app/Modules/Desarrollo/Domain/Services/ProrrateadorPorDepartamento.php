<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Proporción de asistentes de un departamento sobre el total de la actividad, aplicada a costo y beneficio. */
final class ProrrateadorPorDepartamento
{
    public function proporcion(int $asistentesDepartamento, int $asistentesTotal): float
    {
        if ($asistentesTotal <= 0 || $asistentesDepartamento <= 0) {
            return 0.0;
        }

        return min(1.0, $asistentesDepartamento / $asistentesTotal);
    }

    public function aplicar(float $monto, float $proporcion): float
    {
        return $monto * $proporcion;
    }
}
