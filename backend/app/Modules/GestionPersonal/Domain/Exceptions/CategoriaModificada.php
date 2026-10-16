<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class CategoriaModificada extends RuntimeException
{
    public function __construct(string $message = 'La categoría cambió. Actualiza la información.')
    {
        parent::__construct($message);
    }
}
