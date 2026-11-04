<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

final class PuntosPorDebajoDeCategoria extends ValorDeParametroInvalido
{
    public function __construct(int $indice, public readonly string $categoria, public readonly int $puntos)
    {
        parent::__construct($indice, "No puede ser menor que la puntuación de la categoría «{$categoria}» ({$puntos} puntos).");
    }
}
