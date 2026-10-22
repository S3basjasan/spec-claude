<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class CertificadoDTO
{
    /**
     * @param  array<int, string>  $competencias  Nombres vigentes de las competencias vinculadas.
     */
    public function __construct(
        public readonly string $codigo,
        public readonly string $colaborador,
        public readonly string $titulo,
        public readonly array $competencias,
        public readonly int $duracionMinutos,
        public readonly string $fechaFinalizacion,
    ) {}
}
