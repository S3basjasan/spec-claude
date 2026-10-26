<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Una condición actual impide aplicar el movimiento; no hay cambios parciales. */
final class IncidenciaDeAplicacion extends RuntimeException
{
    public const DESTINO_NO_ASIGNABLE = 'DESTINO_NO_ASIGNABLE';

    public const DESTINO_OCUPADO = 'DESTINO_OCUPADO';

    public const VINCULO_NO_VIGENTE = 'VINCULO_NO_VIGENTE';

    public const ORIGEN_CAMBIO = 'ORIGEN_CAMBIO';

    public const ESTRUCTURA_NO_VERIFICABLE = 'ESTRUCTURA_NO_VERIFICABLE';

    public const INCONSISTENCIA = 'INCONSISTENCIA';

    public function __construct(public readonly string $codigo, string $message = 'No se pudo aplicar el movimiento.')
    {
        parent::__construct($message);
    }
}
