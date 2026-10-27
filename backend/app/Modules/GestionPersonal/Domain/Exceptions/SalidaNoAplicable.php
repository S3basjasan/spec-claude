<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Aplicar fuera de estado o antes de la fecha efectiva. */
final class SalidaNoAplicable extends RuntimeException
{
    public function __construct(string $message = 'La salida no puede aplicarse todavía.')
    {
        parent::__construct($message);
    }
}
