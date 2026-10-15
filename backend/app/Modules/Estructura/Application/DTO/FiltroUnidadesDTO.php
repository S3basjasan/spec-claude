<?php

namespace App\Modules\Estructura\Application\DTO;

final class FiltroUnidadesDTO
{
    /**
     * @param  string|null  $estado  `Activa` o `Inactiva`; nulo = todas.
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $estado = null,
    ) {}
}
