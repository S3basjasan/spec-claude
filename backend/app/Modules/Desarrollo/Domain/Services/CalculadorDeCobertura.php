<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\Cobertura;

/**
 * Cobertura formativa: competencias activas con al menos un curso o micro-contenido disponible, sobre las
 * competencias activas totales. Las inactivas no entran en el cálculo.
 */
final class CalculadorDeCobertura
{
    /**
     * @param  array<int, int>  $recursosDisponiblesPorCompetenciaActiva  competencia_id => cantidad de recursos disponibles
     */
    public function calcular(array $recursosDisponiblesPorCompetenciaActiva): Cobertura
    {
        $totales = count($recursosDisponiblesPorCompetenciaActiva);
        $cubiertas = count(array_filter($recursosDisponiblesPorCompetenciaActiva, fn (int $cantidad) => $cantidad >= 1));

        return new Cobertura(
            $totales,
            $cubiertas,
            $totales === 0 ? 0 : (int) round($cubiertas / $totales * 100),
        );
    }
}
