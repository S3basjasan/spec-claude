<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class ListadoDocumentosDTO
{
    /** @param list<DocumentoConCategoriaDTO> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
