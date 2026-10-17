<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;

/**
 * Guía orientativa de la conducta que se reconoce; no es una condición que deba marcar quien otorga el reconocimiento.
 */
final class Indicador
{
    public const MINIMO = 5;

    public const MAXIMO = 250;

    public readonly string $texto;

    public function __construct(string $texto, public readonly int $orden = 0, ?int $posicion = null)
    {
        $recortado = Normalizador::recortar($texto);
        $largo = mb_strlen($recortado);
        $campo = 'indicadores'.($posicion === null ? '' : '.'.$posicion);

        if ($largo < self::MINIMO || $largo > self::MAXIMO) {
            throw new DatosDeCategoriaInvalidos($campo, 'Cada indicador debe tener entre 5 y 250 caracteres.');
        }

        if (Normalizador::contieneHtml($recortado)) {
            throw new DatosDeCategoriaInvalidos($campo, 'El indicador no puede contener etiquetas HTML.');
        }

        $this->texto = $recortado;
    }

    public function normalizado(): string
    {
        return Normalizador::normalizar($this->texto);
    }
}
