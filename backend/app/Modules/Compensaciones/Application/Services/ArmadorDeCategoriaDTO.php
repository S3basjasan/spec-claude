<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Compensaciones\Domain\Entities\Categoria;

/**
 * Resuelve la competencia de cada categoría con una sola lectura al contrato público de Estructura y, cuando el
 * lector puede ver unidades, sus unidades con una sola lectura por lote.
 */
final class ArmadorDeCategoriaDTO
{
    public function __construct(
        private readonly CompetenciasPort $competencias,
        private readonly UnidadesPort $unidades,
    ) {}

    public function uno(Categoria $categoria, bool $conUnidades = false): CategoriaDTO
    {
        return $this->varios([$categoria], $conUnidades)[0];
    }

    /**
     * @param  array<int, Categoria>  $categorias
     * @return array<int, CategoriaDTO>
     */
    public function varios(array $categorias, bool $conUnidades = false): array
    {
        $categorias = array_values($categorias);

        if ($categorias === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(fn (Categoria $c) => $c->competenciaId, $categorias)));
        $competencias = $this->competencias->porIds($ids);

        $unidades = [];
        $activas = [];

        if ($conUnidades) {
            $idsDeUnidades = [];

            foreach ($categorias as $c) {
                foreach ($c->unidades?->ids() ?? [] as $unidadId) {
                    $idsDeUnidades[$unidadId] = $unidadId;
                }
            }

            $unidades = $idsDeUnidades === [] ? [] : $this->unidades->porIds(array_values($idsDeUnidades));
            $activas = array_map(fn (array $u) => $u['id'], $this->unidades->activas());
        }

        return array_map(function (Categoria $c) use ($competencias, $conUnidades, $unidades, $activas) {
            if (! $conUnidades) {
                return new CategoriaDTO($c, $competencias[$c->competenciaId] ?? null);
            }

            $asignadas = $c->unidades?->ids() ?? [];
            $resueltas = [];

            foreach ($asignadas as $unidadId) {
                if (isset($unidades[$unidadId])) {
                    $resueltas[] = [
                        'id' => $unidadId,
                        'nombre' => $unidades[$unidadId]['nombre'],
                        'activa' => $unidades[$unidadId]['estado'] === 'Activo',
                    ];
                }
            }

            $aplicaATodas = $activas !== [] && array_diff($activas, $asignadas) === [];
            $sinActivas = array_filter($resueltas, fn (array $u) => $u['activa']) === [];

            return new CategoriaDTO($c, $competencias[$c->competenciaId] ?? null, $resueltas, $aplicaATodas, $sinActivas);
        }, $categorias);
    }
}
