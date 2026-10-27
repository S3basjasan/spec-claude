<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Una condición actual impide aplicar la salida; no hay cambios parciales. */
final class IncidenciaDeSalida extends RuntimeException
{
    public const VINCULO_NO_VIGENTE = 'VINCULO_NO_VIGENTE';

    public const ASIGNACION_CAMBIO = 'ASIGNACION_CAMBIO';

    public const INCONSISTENCIA = 'INCONSISTENCIA';

    public function __construct(public readonly string $codigo, string $message = 'No se pudo aplicar la salida.', ?\Throwable $previo = null)
    {
        parent::__construct($message, 0, $previo);
    }
}
