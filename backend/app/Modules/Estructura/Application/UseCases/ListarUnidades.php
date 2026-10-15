<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\FiltroUnidadesDTO;
use App\Modules\Estructura\Application\DTO\ListadoUnidadesDTO;
use App\Modules\Estructura\Application\DTO\UnidadDTO;
use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\NormalizadorDeTexto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;

final class ListarUnidades
{
    public function __construct(
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /**
     * El orden jerárquico y el nivel se calculan sobre el árbol completo; el filtro de estado y la búsqueda eligen las
     * coincidencias y a cada una se le añaden las unidades de las que depende, para que el árbol pueda armarse. Las
     * filas conservan su nivel real.
     */
    public function ejecutar(FiltroUnidadesDTO $filtro): ListadoUnidadesDTO
    {
        $estado = $filtro->estado === null ? null : EstadoUnidad::tryFrom($filtro->estado);
        $buscado = $filtro->q === null ? '' : $this->normalizador->paraBuscar($filtro->q);

        $todas = $this->unidades->todas();
        $porId = [];
        foreach ($todas as $unidad) {
            $porId[$unidad->id] = $unidad;
        }

        $coincidencias = [];
        foreach ($todas as $unidad) {
            if (($estado === null || $unidad->estado === $estado) && ($buscado === '' || $this->coincide($unidad, $buscado))) {
                $coincidencias[$unidad->id] = true;
            }
        }

        $visibles = $coincidencias;
        foreach (array_keys($coincidencias) as $id) {
            for ($padre = $porId[$id]->padre?->id; $padre !== null && ! isset($visibles[$padre]); $padre = $porId[$padre]->padre?->id) {
                $visibles[$padre] = true;
            }
        }

        $items = array_values(array_filter($todas, fn (Unidad $unidad) => isset($visibles[$unidad->id])));

        return new ListadoUnidadesDTO(array_map(UnidadDTO::desde(...), $items), count($items), count($coincidencias));
    }

    private function coincide(Unidad $unidad, string $buscado): bool
    {
        foreach ([$unidad->codigo, $unidad->nombre, $unidad->tipo->nombre] as $texto) {
            if (str_contains($this->normalizador->paraBuscar($texto), $buscado)) {
                return true;
            }
        }

        return false;
    }
}
