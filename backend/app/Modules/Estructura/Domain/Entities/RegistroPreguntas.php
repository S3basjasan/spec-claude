<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;

/**
 * Registro de las cuatro preguntas de una competencia. No tiene estado propio: es el de la competencia.
 */
final class RegistroPreguntas
{
    /**
     * @param  array<int, string>  $preguntas  Exactamente cuatro textos, en el orden 1 a 4 (vacío en el listado).
     */
    public function __construct(
        public readonly CompetenciaAsociada $competencia,
        public readonly int $version,
        public readonly ?int $creadoPor = null,
        public readonly ?int $modificadoPor = null,
        public readonly array $preguntas = [],
    ) {}

    public function estado(): EstadoCompetencia
    {
        return $this->competencia->estado;
    }

    public function competenciaActiva(): bool
    {
        return $this->competencia->estado === EstadoCompetencia::Activo;
    }
}
