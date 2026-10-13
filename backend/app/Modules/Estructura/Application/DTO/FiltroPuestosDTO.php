<?php

namespace App\Modules\Estructura\Application\DTO;

final class FiltroPuestosDTO
{
    /**
     * @param  string|null  $q  Código o nombre (contiene, sin distinguir mayúsculas ni tildes).
     * @param  string|null  $estado  `Activo` o `Inactivo`; nulo = todos.
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $estado = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 10,
    ) {}
}
