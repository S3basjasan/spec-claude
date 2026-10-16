<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class CategoriaNoEncontrada extends RuntimeException
{
    public function __construct(string $message = 'La categoría no existe.')
    {
        parent::__construct($message);
    }
}
