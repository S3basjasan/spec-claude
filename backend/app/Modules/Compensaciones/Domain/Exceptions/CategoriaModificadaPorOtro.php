<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

final class CategoriaModificadaPorOtro extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La categoría fue modificada por otro usuario. Recarga para ver los cambios.');
    }
}
