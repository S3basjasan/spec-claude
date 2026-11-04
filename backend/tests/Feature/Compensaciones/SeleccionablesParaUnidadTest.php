<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Presentation\Facades\CategoriasFacade;

function codigosSeleccionablesDeUnaUnidad(int $unidadId): array
{
    return array_column(CategoriasFacade::seleccionablesParaUnidad($unidadId), 'codigo');
}

it('devuelve solo las activas, con competencia activa y asignadas a la unidad', function () {
    $propia = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [1, 2], 'puntuacion_base' => 40]);
    CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [2]]);
    CategoriaReconocimientoModel::factory()->inactiva()->create(['unidades_ids' => [1]]);
    CategoriaReconocimientoModel::factory()->conCompetenciaInactiva()->create(['unidades_ids' => [1]]);
    CategoriaReconocimientoModel::factory()->create(['unidades_ids' => null]);

    $lista = CategoriasFacade::seleccionablesParaUnidad(1);

    expect($lista)->toHaveCount(1)
        ->and($lista[0])->toBe([
            'id' => $propia->id,
            'codigo' => $propia->codigo,
            'nombre' => $propia->nombre,
            'icono' => $propia->icono,
            'competencia_id' => $propia->competencia_id,
            'puntuacion_base' => 40,
        ]);
});

it('devuelve vacío para una unidad inexistente o inactiva, aunque haya categorías asignadas a ella', function () {
    CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [4, 99]]);

    expect(CategoriasFacade::seleccionablesParaUnidad(4))->toBe([])
        ->and(CategoriasFacade::seleccionablesParaUnidad(99))->toBe([]);
});

it('la asignación es directa y refleja de inmediato los cambios de asignación y de estado', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [1]]);

    expect(codigosSeleccionablesDeUnaUnidad(1))->toBe([$categoria->codigo])
        ->and(codigosSeleccionablesDeUnaUnidad(2))->toBe([]);

    $categoria->update(['unidades_ids' => [2]]);
    expect(codigosSeleccionablesDeUnaUnidad(1))->toBe([])
        ->and(codigosSeleccionablesDeUnaUnidad(2))->toBe([$categoria->codigo]);

    $categoria->update(['estado' => 'Inactivo']);
    expect(codigosSeleccionablesDeUnaUnidad(2))->toBe([]);
});

it('al reactivar la unidad vuelve a aplicar la asignación previa', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [4]]);
    expect(codigosSeleccionablesDeUnaUnidad(4))->toBe([]);

    escenarioCompensaciones()->unidades->unidades[4]['estado'] = 'Activo';

    expect(codigosSeleccionablesDeUnaUnidad(4))->toBe([$categoria->codigo]);
});

it('listarSeleccionables no cambia su forma y obtener incluye las unidades para resolver históricos', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [2, 1]]);

    expect(CategoriasFacade::listarSeleccionables()[0])->not->toHaveKey('unidades_ids')
        ->and(CategoriasFacade::obtener($categoria->id)['unidades_ids'])->toBe([1, 2])
        ->and(CategoriasFacade::obtenerPorIds([$categoria->id])[0]['unidades_ids'])->toBe([1, 2]);
});
