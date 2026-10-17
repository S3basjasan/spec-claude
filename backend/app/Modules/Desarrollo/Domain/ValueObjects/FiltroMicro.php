<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/**
 * Filtros del repositorio de micro-contenidos. `estados` ya viene resuelto (por ejemplo «Activos» = Borrador y
 * Publicado); una lista vacía significa «sin restricción de estado».
 */
final class FiltroMicro
{
    public const MAX_POR_PAGINA = 50;

    /**
     * @param  array<int, EstadoMicro>  $estados
     */
    public function __construct(
        public readonly ?TipoMicro $tipo = null,
        public readonly array $estados = [EstadoMicro::Borrador, EstadoMicro::Publicado],
        public readonly ?int $competenciaId = null,
        public readonly ?string $q = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 12,
    ) {}
}
