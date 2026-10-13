<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Función de un puesto. El orden empieza en 1 y lo define el usuario.
 */
final class FuncionPuesto
{
    public function __construct(
        public readonly int $orden,
        public readonly string $descripcion,
        public readonly TipoFuncion $tipo,
    ) {}

    /** Clave para comparar: sin distinguir mayúsculas ni espacios al inicio o al final. */
    public function clave(): string
    {
        return mb_strtolower(trim($this->descripcion));
    }
}
