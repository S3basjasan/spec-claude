<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Competencia del diccionario con los grados que define. Solo lectura: se obtiene del diccionario de
 * competencias en cada consulta y nunca se almacena en el catálogo de puestos.
 */
final class CompetenciaConGrados
{
    /**
     * @param  array<string, string>  $grados  Mapa letra (A–D) → descripción del grado.
     */
    public function __construct(
        public readonly CompetenciaAsociada $competencia,
        public readonly array $grados,
    ) {}

    public function tieneGrado(string $grado): bool
    {
        return array_key_exists($grado, $this->grados);
    }
}
