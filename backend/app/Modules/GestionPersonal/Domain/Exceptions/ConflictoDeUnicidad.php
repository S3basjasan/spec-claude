<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class ConflictoDeUnicidad extends RuntimeException
{
    public function __construct(string $message = 'Conflicto de unicidad.')
    {
        parent::__construct($message);
    }
}
