<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use DateTimeImmutable;

/** Calcula el estado de una acción a partir de la cancelación y de sus fechas respecto a hoy (no se guarda). */
final class CalculadorDeEstadoDeAccion
{
    public function calcular(bool $cancelada, ?DateTimeImmutable $inicio, ?DateTimeImmutable $fin, DateTimeImmutable $hoy): EstadoAccion
    {
        if ($cancelada) {
            return EstadoAccion::Cancelada;
        }
        $hoy = $hoy->setTime(0, 0);
        if ($inicio === null || $fin === null || $hoy < $inicio->setTime(0, 0)) {
            return EstadoAccion::Programada;
        }

        return $hoy > $fin->setTime(0, 0) ? EstadoAccion::Finalizada : EstadoAccion::EnCurso;
    }
}
