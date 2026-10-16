<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;

final class DocumentoConCategoriaDTO
{
    /**
     * @param  array{id: int, nombre: string, estado: string}|null  $categoria  Nombre vigente, aunque esté inactiva.
     * @param  bool  $creado  Solo en cargas: false si fue la repetición de una operación ya completada.
     */
    public function __construct(
        public readonly DocumentoColaborador $documento,
        public readonly ?array $categoria,
        public readonly bool $creado = false,
    ) {}
}
