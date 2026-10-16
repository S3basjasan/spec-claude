<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class DatosInvalidos extends RuntimeException
{
    /** @param array<string, list<string>> $errores */
    public function __construct(public readonly array $errores, string $message = 'Los datos no son válidos.')
    {
        parent::__construct($message);
    }
}
