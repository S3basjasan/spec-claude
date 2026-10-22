<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

/**
 * Otro proceso tomó el mismo código CAT-### al mismo tiempo; el caso de uso reintenta con el siguiente.
 */
final class CodigoDeCategoriaEnUso extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No se pudo asignar un código a la categoría. Inténtalo de nuevo.');
    }
}
