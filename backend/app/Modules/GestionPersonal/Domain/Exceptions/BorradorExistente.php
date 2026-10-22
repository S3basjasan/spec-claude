<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class BorradorExistente extends RuntimeException
{
    public function __construct(public readonly int $borradorId, string $message = 'Ya existe un borrador para este tipo. Continúalo o descártalo.')
    {
        parent::__construct($message);
    }
}
