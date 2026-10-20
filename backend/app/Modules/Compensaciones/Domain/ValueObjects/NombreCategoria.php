<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;

final class NombreCategoria
{
    public const MINIMO = 3;

    public const MAXIMO = 100;

    public readonly string $valor;

    public function __construct(string $valor)
    {
        $recortado = Normalizador::recortar($valor);
        $largo = mb_strlen($recortado);

        if ($largo < self::MINIMO || $largo > self::MAXIMO) {
            throw new DatosDeCategoriaInvalidos('nombre', 'El nombre debe tener entre 3 y 100 caracteres.');
        }

        if (Normalizador::contieneHtml($recortado)) {
            throw new DatosDeCategoriaInvalidos('nombre', 'El nombre no puede contener etiquetas HTML.');
        }

        $this->valor = $recortado;
    }

    public function normalizado(): string
    {
        return Normalizador::normalizar($this->valor);
    }
}
