<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La ausencia informada tiene una fecha posterior a hoy. */
final class AusenciaFutura extends RuntimeException
{
    public function __construct(string $message = 'Una ausencia solo puede informarse una vez ocurrida.')
    {
        parent::__construct($message);
    }
}
