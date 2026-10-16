<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Estado auditable de una posición en un momento dado: los campos que este módulo registra en el historial.
 */
final class InstantaneaDePosicion
{
    public function __construct(
        public readonly int $puestoId,
        public readonly int $unidadId,
        public readonly ?int $supervisorId,
        public readonly Modalidad $modalidad,
        public readonly EstadoPosicion $estado,
    ) {}
}
