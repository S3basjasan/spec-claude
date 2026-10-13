<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Un cambio que queda en el historial: el campo afectado y su valor anterior y nuevo (nulo en altas o bajas).
 */
final class CambioAuditado
{
    public function __construct(
        public readonly string $campo,
        public readonly ?string $valorAnterior,
        public readonly ?string $valorNuevo,
    ) {}
}
