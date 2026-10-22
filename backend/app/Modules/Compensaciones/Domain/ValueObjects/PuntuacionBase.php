<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;

/**
 * Valor de ordenamiento del ranking interno: entero de 1 a 1000. No es dinero ni se canjea.
 */
final class PuntuacionBase
{
    public const MINIMA = 1;

    public const MAXIMA = 1000;

    public readonly int $valor;

    public function __construct(mixed $valor)
    {
        if (! is_int($valor) && ! (is_string($valor) && preg_match('/^\d+$/', $valor) === 1)) {
            throw new DatosDeCategoriaInvalidos('puntuacion_base', 'La puntuación base debe ser un número entero.');
        }

        $entero = (int) $valor;

        if ($entero < self::MINIMA || $entero > self::MAXIMA) {
            throw new DatosDeCategoriaInvalidos('puntuacion_base', 'La puntuación base debe estar entre 1 y 1000.');
        }

        $this->valor = $entero;
    }
}
