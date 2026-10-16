<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class CuentaSinFicha extends RuntimeException
{
    public function __construct(string $message = 'Tu cuenta no tiene una ficha asociada.')
    {
        parent::__construct($message);
    }
}
