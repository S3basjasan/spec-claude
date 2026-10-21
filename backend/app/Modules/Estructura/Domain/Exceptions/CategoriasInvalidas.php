<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class CategoriasInvalidas extends RuntimeException
{
    public const INCOMPLETAS = 'Escribe las cuatro categorías, sin repetir nombres.';

    public const DEMASIADO_LARGO = 'Un nombre de categoría no puede superar los 20 caracteres.';

    public function __construct(string $mensaje = self::INCOMPLETAS)
    {
        parent::__construct($mensaje);
    }
}
