<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Grado de una competencia con su etiqueta según la escala y sus comportamientos. `id` es el
 * `competencia_grado_id` que otros módulos referencian como grado requerido.
 */
final class GradoDeCompetencia
{
    /**
     * @param  array<int, string>  $comportamientos  Textos en su orden (hasta cuatro).
     */
    public function __construct(
        public readonly int $id,
        public readonly int $orden,
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly string $descripcion,
        public readonly array $comportamientos = [],
    ) {}
}
