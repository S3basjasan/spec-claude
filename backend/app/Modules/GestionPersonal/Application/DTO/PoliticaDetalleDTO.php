<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class PoliticaDetalleDTO
{
    /**
     * @param  array<string, mixed>  $definicion
     * @param  list<VersionDTO>  $versiones  Publicadas, de la más reciente a la más antigua.
     */
    public function __construct(
        public readonly PoliticaResumenDTO $resumen,
        public readonly array $definicion,
        public readonly array $versiones,
    ) {}
}
