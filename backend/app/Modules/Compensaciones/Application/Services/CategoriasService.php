<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\Indicador;

/**
 * Contrato público del catálogo para Desafíos, Hitos y Ranking (detrás de `CategoriasFacade`).
 * Solo lectura: nadie más mantiene su propio catálogo de categorías.
 */
class CategoriasService
{
    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly CompetenciasPort $competencias,
        private readonly UnidadesPort $unidades,
    ) {}

    /**
     * Categorías Activas cuya competencia está activa, por código: las únicas válidas para nuevos registros.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, icono: string, competencia_id: int, puntuacion_base: int}>
     */
    public function listarSeleccionables(): array
    {
        return array_map(function (array $c) {
            unset($c['unidades_ids']);

            return $c;
        }, $this->seleccionables());
    }

    /**
     * Seleccionables con las unidades a las que aplica cada una (uso interno).
     *
     * @return array<int, array<string, mixed>>
     */
    private function seleccionables(): array
    {
        $activas = $this->categorias->activas();
        $competencias = $this->competencias->porIds(array_map(fn (Categoria $c) => $c->competenciaId, $activas));

        $seleccionables = array_filter(
            $activas,
            fn (Categoria $c) => $c->esSeleccionable(($competencias[$c->competenciaId]['estado'] ?? null) === 'Activo'),
        );

        return array_values(array_map(fn (Categoria $c) => [
            'id' => (int) $c->id,
            'codigo' => (string) $c->codigo,
            'nombre' => $c->nombre->valor,
            'icono' => $c->icono->value,
            'competencia_id' => $c->competenciaId,
            'puntuacion_base' => $c->puntuacion->valor,
            'unidades_ids' => $c->unidades?->ids() ?? [],
        ], $seleccionables));
    }

    /**
     * Categorías seleccionables para reconocer o asignar un desafío a una persona de la unidad: activas, con su competencia
     * activa y asignadas directamente a esa unidad (sin herencia por jerarquía). Unidad inexistente o inactiva: vacío.
     * Quien registra (desafíos 02.3, reconocimientos 03.1) rechaza con 422 toda categoría que no esté en esta lista.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, icono: string, competencia_id: int, puntuacion_base: int}>
     */
    public function seleccionablesParaUnidad(int $unidadId): array
    {
        if (($this->unidades->porIds([$unidadId])[$unidadId]['estado'] ?? null) !== 'Activo') {
            return [];
        }

        $asignadas = array_filter($this->seleccionables(), fn (array $c) => in_array($unidadId, $c['unidades_ids'], true));

        return array_values(array_map(function (array $c) {
            unset($c['unidades_ids']);

            return $c;
        }, $asignadas));
    }

    /**
     * Categoría en cualquier estado (resuelve registros históricos), o null si no existe.
     *
     * @return array<string, mixed>|null
     */
    public function obtener(int $id): ?array
    {
        $categoria = $this->categorias->porId($id);

        return $categoria === null ? null : $this->aArreglo($categoria, $this->competencias->porIds([$categoria->competenciaId]));
    }

    /**
     * Categorías existentes entre los ids dados, en cualquier estado.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPorIds(array $ids): array
    {
        $categorias = $this->categorias->porIds(array_values(array_unique($ids)));
        $competencias = $this->competencias->porIds(array_map(fn (Categoria $c) => $c->competenciaId, $categorias));

        return array_map(fn (Categoria $c) => $this->aArreglo($c, $competencias), $categorias);
    }

    /** Verdadero solo si existe, está Activa y su competencia está activa. */
    public function esSeleccionable(int $id): bool
    {
        return (bool) ($this->obtener($id)['seleccionable'] ?? false);
    }

    /**
     * @param  array<int, array{id: int, codigo: string, nombre: string, tipo: string, estado: string}>  $competencias
     * @return array<string, mixed>
     */
    private function aArreglo(Categoria $c, array $competencias): array
    {
        $competenciaActiva = ($competencias[$c->competenciaId]['estado'] ?? null) === 'Activo';

        return [
            'id' => (int) $c->id,
            'codigo' => (string) $c->codigo,
            'nombre' => $c->nombre->valor,
            'descripcion' => $c->descripcion,
            'icono' => $c->icono->value,
            'competencia_id' => $c->competenciaId,
            'competencia_activa' => $competenciaActiva,
            'estado' => $c->estado->value,
            'seleccionable' => $c->esSeleccionable($competenciaActiva),
            'puntuacion_base' => $c->puntuacion->valor,
            'indicadores' => array_map(fn (Indicador $i) => ['orden' => $i->orden, 'texto' => $i->texto], $c->indicadores),
            'unidades_ids' => $c->unidades?->ids() ?? [],
        ];
    }
}
