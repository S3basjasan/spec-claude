<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** La estructura del plan solo se edita en Borrador (FR-031, FR-034). */
final class PdiNoEditable extends TransicionInvalidaDePdi
{
    public function __construct()
    {
        parent::__construct('El plan solo se puede editar mientras está en Borrador.');
    }
}
