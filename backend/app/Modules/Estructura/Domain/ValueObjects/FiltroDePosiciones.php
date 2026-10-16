<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Criterios del listado de posiciones. `estado` ya viene resuelto según el rol: `Vigentes` (Ocupadas y Vacantes),
 * `Ocupada`, `Vacante`, `Suprimida` o `Todas`.
 */
final class FiltroDePosiciones
{
    public const VIGENTES = 'Vigentes';

    public const TODAS = 'Todas';

    public const POR_PAGINA = 10;

    public function __construct(
        public readonly ?string $q = null,
        public readonly string $estado = self::VIGENTES,
        public readonly ?int $unidadId = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = self::POR_PAGINA,
        /** @var array<int, int> Ids de usuarios cuyo nombre coincide con `q` (se buscan por el puerto de usuarios). */
        public readonly array $usuarioIds = [],
    ) {}
}
