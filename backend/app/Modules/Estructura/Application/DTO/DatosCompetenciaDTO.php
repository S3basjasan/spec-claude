<?php

namespace App\Modules\Estructura\Application\DTO;

final class DatosCompetenciaDTO
{
    /**
     * @param  array<string, string>  $grados  Descripción por letra A–D.
     */
    public function __construct(
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly array $grados,
    ) {}
}
