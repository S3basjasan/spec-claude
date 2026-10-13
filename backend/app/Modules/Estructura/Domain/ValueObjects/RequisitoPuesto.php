<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Requisito de un puesto (formación, experiencia o capacitación).
 */
final class RequisitoPuesto
{
    public function __construct(
        public readonly TipoRequisito $tipo,
        public readonly string $descripcion,
    ) {}

    /** Clave para comparar: el tipo más la descripción sin distinguir mayúsculas ni espacios alrededor. */
    public function clave(): string
    {
        return $this->tipo->value.'|'.mb_strtolower(trim($this->descripcion));
    }
}
