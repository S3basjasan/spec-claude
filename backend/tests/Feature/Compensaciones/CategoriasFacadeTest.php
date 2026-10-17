<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Presentation\Facades\CategoriasFacade;
use App\Modules\Estructura\Application\Services\EstructuraService;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Database\Seeders\CategoriasReconocimientoSeeder;
use Database\Seeders\CompetenciasSeeder;
use Database\Seeders\UsuariosInicialesSeeder;

it('lista solo las categorías seleccionables: activas con competencia activa', function () {
    $seleccionable = CategoriaReconocimientoModel::factory()->create(['nombre' => 'Seleccionable', 'puntuacion_base' => 120]);
    CategoriaReconocimientoModel::factory()->inactiva()->create(['nombre' => 'Inactiva']);
    CategoriaReconocimientoModel::factory()->conCompetenciaInactiva()->create(['nombre' => 'Competencia inactiva']);

    $lista = CategoriasFacade::listarSeleccionables();

    expect($lista)->toHaveCount(1)
        ->and($lista[0])->toMatchArray([
            'id' => $seleccionable->id,
            'nombre' => 'Seleccionable',
            'puntuacion_base' => 120,
            'competencia_id' => $seleccionable->competencia_id,
        ]);
});

it('resuelve cualquier categoría para los registros históricos', function () {
    $inactiva = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 80]);
    $conCompetenciaInactiva = CategoriaReconocimientoModel::factory()->conCompetenciaInactiva()->create();

    $a = CategoriasFacade::obtener($inactiva->id);
    $b = CategoriasFacade::obtener($conCompetenciaInactiva->id);

    expect($a)->toMatchArray(['estado' => 'Inactivo', 'seleccionable' => false, 'competencia_activa' => true, 'puntuacion_base' => 80])
        ->and($a['indicadores'])->toHaveCount(2)
        ->and($b)->toMatchArray(['estado' => 'Activo', 'seleccionable' => false, 'competencia_activa' => false])
        ->and(CategoriasFacade::obtener(9999))->toBeNull();
});

it('devuelve varias categorías por id en cualquier estado', function () {
    $activa = CategoriaReconocimientoModel::factory()->create();
    $inactiva = CategoriaReconocimientoModel::factory()->inactiva()->create();

    $resultado = CategoriasFacade::obtenerPorIds([$activa->id, $inactiva->id, $inactiva->id, 9999]);

    expect(collect($resultado)->pluck('id')->sort()->values()->all())->toBe([$activa->id, $inactiva->id]);
});

it('indica si una categoría es seleccionable', function () {
    $activa = CategoriaReconocimientoModel::factory()->create();
    $inactiva = CategoriaReconocimientoModel::factory()->inactiva()->create();

    expect(CategoriasFacade::esSeleccionable($activa->id))->toBeTrue()
        ->and(CategoriasFacade::esSeleccionable($inactiva->id))->toBeFalse()
        ->and(CategoriasFacade::esSeleccionable(9999))->toBeFalse();
});

it('vuelve a ser seleccionable al reactivarse la competencia, sin guardar un tercer estado', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    $competencia = CompetenciaModel::query()->findOrFail($categoria->competencia_id);

    $competencia->update(['estado' => 'Inactivo']);
    expect(CategoriasFacade::esSeleccionable($categoria->id))->toBeFalse()
        ->and($categoria->fresh()->estado)->toBe('Activo');

    $competencia->update(['estado' => 'Activo']);
    expect(CategoriasFacade::esSeleccionable($categoria->id))->toBeTrue();
});

it('no altera la puntuación ya copiada por otro módulo cuando la categoría cambia', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 100]);
    $copiaEnElHito = CategoriasFacade::obtener($categoria->id)['puntuacion_base'];

    $categoria->update(['puntuacion_base' => 900]);

    expect($copiaEnElHito)->toBe(100)
        ->and(CategoriasFacade::obtener($categoria->id)['puntuacion_base'])->toBe(900);
});

it('lee competencias solo a través del contrato público de Estructura', function () {
    expect(app('estructura-facade'))->toBeInstanceOf(EstructuraService::class);

    $archivos = glob(app_path('Modules/Compensaciones/{Application,Domain,Infrastructure,Presentation}/{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE);
    $infracciones = collect($archivos)->filter(function (string $archivo) {
        $codigo = file_get_contents($archivo);

        return str_contains($codigo, 'Estructura\\Infrastructure') || str_contains($codigo, "table('competencias')") || str_contains($codigo, 'CompetenciaModel');
    });

    expect($infracciones->all())->toBe([]);
});

it('siembra categorías de prueba que cubren los casos de la paginación y la competencia inactiva', function () {
    $this->seed(UsuariosInicialesSeeder::class);
    $this->seed(CompetenciasSeeder::class);
    $this->seed(CategoriasReconocimientoSeeder::class);
    $this->seed(CategoriasReconocimientoSeeder::class); // idempotente

    expect(CategoriaReconocimientoModel::query()->count())->toBe(20)
        ->and(CategoriaReconocimientoModel::query()->where('estado', 'Inactivo')->count())->toBe(3)
        ->and(CategoriaReconocimientoModel::query()->withCount('indicadores')->get()->pluck('indicadores_count')->unique()->sort()->values()->all())->toBe([1, 2, 3, 4, 5]);

    $conCompetenciaInactiva = collect(CategoriasFacade::obtenerPorIds(CategoriaReconocimientoModel::query()->pluck('id')->all()))
        ->filter(fn ($c) => $c['estado'] === 'Activo' && ! $c['competencia_activa']);

    expect($conCompetenciaInactiva)->toHaveCount(1);
});
