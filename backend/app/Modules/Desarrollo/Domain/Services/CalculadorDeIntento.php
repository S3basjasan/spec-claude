<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Reglas puras de un intento de quiz: opción correcta, puntaje y fin del intento. */
final class CalculadorDeIntento
{
    /**
     * @param  array<int, array{id: int, es_correcta: bool}>  $opciones
     */
    public function opcionCorrectaId(array $opciones): ?int
    {
        foreach ($opciones as $o) {
            if ($o['es_correcta']) {
                return (int) $o['id'];
            }
        }

        return null;
    }

    /**
     * Respuestas correctas sobre el total de respuestas dadas.
     *
     * @param  array<int, array{es_correcta: bool}>  $respondidas
     */
    public function puntaje(array $respondidas): int
    {
        return count(array_filter($respondidas, fn (array $r) => $r['es_correcta']));
    }

    /** Un intento termina al responder todas las preguntas del quiz. */
    public function terminado(int $respondidas, int $totalPreguntas): bool
    {
        return $totalPreguntas > 0 && $respondidas >= $totalPreguntas;
    }
}
