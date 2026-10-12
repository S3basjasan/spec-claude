<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Datos de la competencia asociada a un registro de comportamientos. Solo lectura: se obtienen del
 * diccionario de competencias en cada consulta y nunca se almacenan en este diccionario.
 */
final class CompetenciaAsociada
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly TipoCompetencia $tipo,
        public readonly EstadoCompetencia $estado,
    ) {}
}
