<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class FiltroCategoriasDTO
{
    public function __construct(
        public readonly ?string $q,
        /** @var 'Activa'|'Inactiva'|'Todas' */
        public readonly string $estado = 'Activa',
        public readonly int $pagina = 1,
        public readonly int $porPagina = 20,
    ) {}
}
