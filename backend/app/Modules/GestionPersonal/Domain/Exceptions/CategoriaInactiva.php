<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La categoría no existe para el tenant o está inactiva: no admite nuevas clasificaciones. */
final class CategoriaInactiva extends RuntimeException
{
    public function __construct(string $message = 'La categoría seleccionada ya no está disponible. Elige otra categoría activa.')
    {
        parent::__construct($message);
    }
}
