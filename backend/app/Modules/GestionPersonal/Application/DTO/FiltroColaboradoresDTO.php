<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class FiltroColaboradoresDTO
{
    public function __construct(
        public readonly ?string $q,
        public readonly ?string $estado,
        public readonly ?int $unidadId,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 20,
    ) {}
}
