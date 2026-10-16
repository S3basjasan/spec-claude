<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class FiltroDocumentosDTO
{
    public function __construct(
        public readonly ?string $q,
        public readonly ?int $categoriaId,
        /** @var 'Disponible'|'Archivado' */
        public readonly string $estado = 'Disponible',
        public readonly int $pagina = 1,
        public readonly int $porPagina = 20,
    ) {}
}
