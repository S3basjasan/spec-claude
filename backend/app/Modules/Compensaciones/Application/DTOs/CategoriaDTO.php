<?php

namespace App\Modules\Compensaciones\Application\DTOs;

use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\ValueObjects\Indicador;

/**
 * Categoría con su competencia ya resuelta y los valores derivados (`competencia_activa`, `seleccionable`).
 * Cuando el lector puede ver las unidades también trae las unidades resueltas y los indicadores derivados.
 */
final class CategoriaDTO
{
    /**
     * @param  array{id: int, codigo: string, nombre: string, tipo: string, estado: string}|null  $competencia
     * @param  array<int, array{id: int, nombre: string, activa: bool}>|null  $unidades  null cuando el lector no ve unidades
     */
    public function __construct(
        public readonly Categoria $categoria,
        public readonly ?array $competencia,
        public readonly ?array $unidades = null,
        public readonly bool $aplicaATodas = false,
        public readonly bool $sinUnidadesActivas = false,
    ) {}

    public function competenciaActiva(): bool
    {
        return ($this->competencia['estado'] ?? null) === 'Activo';
    }

    public function seleccionable(): bool
    {
        return $this->categoria->esSeleccionable($this->competenciaActiva());
    }

    /**
     * @return array<int, array{orden: int, texto: string}>
     */
    public function indicadores(): array
    {
        return array_map(
            fn (Indicador $i) => ['orden' => $i->orden, 'texto' => $i->texto],
            $this->categoria->indicadores,
        );
    }
}
