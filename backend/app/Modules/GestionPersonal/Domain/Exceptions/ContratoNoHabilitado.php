<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ContratoNoHabilitado extends RuntimeException
{
    public function __construct(string $message = 'El tipo de contrato no está habilitado.')
    {
        parent::__construct($message);
    }
}
