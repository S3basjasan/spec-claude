<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;

/**
 * Valor de ordenamiento del ranking interno: entero de 1 al máximo permitido. No es dinero ni se canjea.
 *
 * `MAXIMA` (1000) es el límite exterior y el máximo por defecto: reconstruir una categoría ya guardada no falla aunque
 * supere el tope vigente. Al guardar o activar, Aplicación pasa el máximo permitido (puntos por colaborador).
 */
final class PuntuacionBase
{
    public const MINIMA = 1;

    public const MAXIMA = 1000;

    public readonly int $valor;

    public function __construct(mixed $valor, int $maximo = self::MAXIMA)
    {
        if (! is_int($valor) && ! (is_string($valor) && preg_match('/^\d+$/', $valor) === 1)) {
            throw new DatosDeCategoriaInvalidos('puntuacion_base', 'La puntuación base debe ser un número entero.');
        }

        $entero = (int) $valor;

        if ($entero < self::MINIMA || $entero > $maximo) {
            throw new DatosDeCategoriaInvalidos('puntuacion_base', "La puntuación base debe estar entre 1 y {$maximo}.");
        }

        $this->valor = $entero;
    }
}
