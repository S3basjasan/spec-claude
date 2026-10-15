<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * No se puede reactivar la unidad porque su unidad padre está inactiva.
 */
final class UnidadNoReactivable extends RuntimeException
{
    public const PADRE_INACTIVO = 'padre_inactivo';

    public function __construct(public readonly string $motivo = self::PADRE_INACTIVO)
    {
        parent::__construct('No se puede reactivar la unidad: su unidad padre está inactiva.');
    }
}
