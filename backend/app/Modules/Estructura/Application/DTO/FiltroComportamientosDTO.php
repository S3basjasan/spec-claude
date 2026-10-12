<?php

namespace App\Modules\Estructura\Application\DTO;

final class FiltroComportamientosDTO
{
    /**
     * @param  ?string  $estado  `Activo`, `Inactivo` o null (todos). Lo resuelve el borde de entrada según el rol.
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $tipo = null,
        public readonly ?string $estado = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 10,
    ) {}
}
