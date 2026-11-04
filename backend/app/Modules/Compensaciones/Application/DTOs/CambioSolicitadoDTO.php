<?php

namespace App\Modules\Compensaciones\Application\DTOs;

use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;

/** Un cambio pedido por el Gestor: el valor nuevo y la versión de la clave que leyó. */
final class CambioSolicitadoDTO
{
    public function __construct(
        public readonly ClaveDeParametro $clave,
        public readonly int $valor,
        public readonly int $version,
    ) {}
}
