<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La ficha ya tiene un vínculo vigente o programado que se superpone con el ingreso. */
final class VinculoSuperpuesto extends RuntimeException
{
    public function __construct(string $message = 'La ficha ya tiene un vínculo laboral que se superpone con esa fecha.')
    {
        parent::__construct($message);
    }
}
