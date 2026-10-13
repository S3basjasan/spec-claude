<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Competencia asignada a un puesto con su grado requerido. Los datos de la competencia son los actuales
 * del diccionario (solo lectura); el puesto guarda únicamente la competencia y el grado.
 */
final class CompetenciaDePuesto
{
    public function __construct(
        public readonly CompetenciaAsociada $competencia,
        public readonly string $gradoRequerido,
    ) {}
}
