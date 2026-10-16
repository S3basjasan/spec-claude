<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Datos de una posición que llegan del Administrador: puesto (solo al crear), unidad, supervisor (nulo solo en la
 * máxima autoridad) y modalidad. El estado, el código y el ocupante nunca vienen del cliente.
 */
final class DatosPosicion
{
    public function __construct(
        public readonly ?int $puestoId,
        public readonly int $unidadId,
        public readonly ?int $supervisorId,
        public readonly Modalidad $modalidad,
    ) {}
}
