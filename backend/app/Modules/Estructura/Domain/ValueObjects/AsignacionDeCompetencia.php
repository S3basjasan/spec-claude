<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Competencia solicitada para un puesto: su identificador y el grado requerido.
 */
final class AsignacionDeCompetencia
{
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $grado,
    ) {}
}
