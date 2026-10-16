<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class NombreDeCategoriaDuplicado extends RuntimeException
{
    public function __construct(string $message = 'Ya existe una categoría con ese nombre.')
    {
        parent::__construct($message);
    }
}
