<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * Las posiciones vigentes no forman un único árbol (sin raíz, varias raíces, posiciones inalcanzables o un ciclo). Es un
 * error de datos del módulo de posiciones: el organigrama no lo corrige. El mensaje es genérico; los ids van aparte.
 */
final class OrganigramaInconsistente extends RuntimeException
{
    public const SIN_RAIZ = 'sin_raiz';

    public const VARIAS_RAICES = 'varias_raices';

    public const INALCANZABLES = 'inalcanzables';

    public const CICLO = 'ciclo';

    /**
     * @param  array<int, int>  $ids  Posiciones afectadas (para el registro del servidor, nunca para el cliente).
     */
    public function __construct(public readonly string $motivo, public readonly array $ids = [])
    {
        parent::__construct('No se pudo generar el organigrama.');
    }
}
