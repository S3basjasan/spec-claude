<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La fecha efectiva es anterior a hoy o al inicio del vínculo. */
final class FechaEfectivaInvalida extends RuntimeException
{
    public function __construct(string $message = 'La fecha efectiva no es válida.')
    {
        parent::__construct($message);
    }
}
