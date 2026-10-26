<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La ficha no tiene un vínculo laboral vigente. */
final class SinVinculoVigente extends RuntimeException
{
    public function __construct(string $message = 'La persona no tiene un vínculo laboral vigente.')
    {
        parent::__construct($message);
    }
}
