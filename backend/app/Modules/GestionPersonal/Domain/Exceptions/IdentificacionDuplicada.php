<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

final class IdentificacionDuplicada extends RuntimeException
{
    public function __construct(public readonly int $fichaId, public readonly string $fichaCodigo)
    {
        parent::__construct('Ya existe una ficha con ese tipo y número de identificación.');
    }
}
