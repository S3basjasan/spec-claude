<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Resumen de aprendizaje del colaborador: contadores y horas acumuladas. */
final class CalculadorDeResumen
{
    /**
     * Horas acumuladas = suma de la duración de los completados, en horas con un decimal.
     *
     * @return array{completados: int, en_progreso: int, horas_acumuladas: float, certificados: int}
     */
    public function resumir(int $completados, int $enProgreso, int $minutosCompletados): array
    {
        return [
            'completados' => $completados,
            'en_progreso' => $enProgreso,
            'horas_acumuladas' => round($minutosCompletados / 60, 1),
            'certificados' => $completados,
        ];
    }
}
