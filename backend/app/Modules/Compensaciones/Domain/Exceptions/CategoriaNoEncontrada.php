<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use RuntimeException;

final class CategoriaNoEncontrada extends RuntimeException
{
    public function __construct(string $message = 'La categoría de reconocimiento no existe.')
    {
        parent::__construct($message);
    }
}
