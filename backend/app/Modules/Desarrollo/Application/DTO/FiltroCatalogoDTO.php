<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** Filtros del listado del catálogo y contexto del solicitante (rol e id de usuario). */
final class FiltroCatalogoDTO
{
    /**
     * @param  string|null  $estado  `Activo` | `Inactivo` | null (todos); para quien no es admin siempre `Activo`
     * @param  string|null  $cobertura  `con` | `sin` (cursos disponibles) | `sin_oferta` (ni cursos ni micro-contenidos)
     */
    public function __construct(
        public readonly ?string $q,
        public readonly ?string $tipo,
        public readonly ?int $areaFormativaId,
        public readonly ?string $cobertura,
        public readonly ?string $estado,
        public readonly ?int $competenciaId,
        public readonly int $pagina,
        public readonly int $porPagina,
        public readonly bool $esAdmin,
        public readonly int $usuarioId,
    ) {}
}
