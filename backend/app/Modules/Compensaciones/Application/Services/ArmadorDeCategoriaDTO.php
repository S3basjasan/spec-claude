<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Domain\Entities\Categoria;

/**
 * Resuelve la competencia de cada categoría con una sola lectura al contrato público de Estructura.
 */
final class ArmadorDeCategoriaDTO
{
    public function __construct(private readonly CompetenciasPort $competencias) {}

    public function uno(Categoria $categoria): CategoriaDTO
    {
        return new CategoriaDTO($categoria, $this->competencias->porId($categoria->competenciaId));
    }

    /**
     * @param  array<int, Categoria>  $categorias
     * @return array<int, CategoriaDTO>
     */
    public function varios(array $categorias): array
    {
        $ids = array_values(array_unique(array_map(fn (Categoria $c) => $c->competenciaId, $categorias)));
        $competencias = $this->competencias->porIds($ids);

        return array_map(
            fn (Categoria $c) => new CategoriaDTO($c, $competencias[$c->competenciaId] ?? null),
            array_values($categorias),
        );
    }
}
