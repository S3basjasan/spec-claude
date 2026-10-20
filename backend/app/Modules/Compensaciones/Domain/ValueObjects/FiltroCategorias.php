<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

/**
 * Filtros del listado. `texto` ya viene normalizado; `competenciaIdsPorNombre` son las competencias cuyo
 * nombre coincide con la búsqueda (resueltas por el puerto de competencias, sin JOIN).
 */
final class FiltroCategorias
{
    public const POR_PAGINA = 15;

    /**
     * @param  array<int, int>  $competenciaIdsPorNombre
     */
    public function __construct(
        public readonly ?string $texto,
        public readonly ?EstadoCategoria $estado,
        public readonly ?int $competenciaId,
        public readonly int $pagina,
        public readonly array $competenciaIdsPorNombre = [],
    ) {}
}
