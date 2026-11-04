<?php

use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use Carbon\Carbon;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

afterEach(fn () => Carbon::setTestNow());

it('calcula el máximo permitido: el menor entre el vigente y el programado', function (?int $programado, int $esperado) {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    if ($programado !== null) {
        putParametro('puntos_por_colaborador', $programado)->assertOk();
    }

    expect(app(ParametrosDelModulo::class)->puntuacionMaximaDeCategoria())->toBe($esperado);
})->with([
    'sin programado' => [null, 100],
    'programado menor' => [60, 60],
    'programado mayor' => [150, 100],
    'programado igual' => [100, 100],
]);

it('tras el cambio de mes el programado pasa a ser el máximo', function () {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    putParametro('puntos_por_colaborador', 150)->assertOk();
    expect(app(ParametrosDelModulo::class)->puntuacionMaximaDeCategoria())->toBe(100);

    Carbon::setTestNow('2026-12-01 08:00:00');

    expect(app(ParametrosDelModulo::class)->puntuacionMaximaDeCategoria())->toBe(150);
});

it('crea y edita con la puntuación igual al máximo y rechaza una más', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 50]);

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 100]))->assertCreated();
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['puntuacion_base' => 100]))->assertOk();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['nombre' => 'Otra categoría', 'puntuacion_base' => 101]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'La puntuación base debe estar entre 1 y 100.');
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria->fresh(), ['puntuacion_base' => 101]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'La puntuación base debe estar entre 1 y 100.');

    expect($categoria->fresh()->puntuacion_base)->toBe(100)
        ->and(CategoriaReconocimientoModel::query()->count())->toBe(2);
});

it('acepta el límite inferior 1', function () {
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 1]))
        ->assertCreated()
        ->assertJsonPath('data.puntuacion_base', 1);
});

it('con un cambio programado menor, el máximo es el programado', function () {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    putParametro('puntos_por_colaborador', 60)->assertOk();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 61]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'La puntuación base debe estar entre 1 y 60.');
    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 60]))->assertCreated();
});

it('con un cambio programado mayor, el máximo sigue siendo el vigente', function () {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    putParametro('puntos_por_colaborador', 150)->assertOk();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 101]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'La puntuación base debe estar entre 1 y 100.');
    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 100]))->assertCreated();
});

it('usa el máximo del momento de guardar y no el que se mostró al abrir el formulario', function () {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 90]))->assertCreated();

    // Con el formulario aún mostrando 100, otro Gestor programa 90 (el piso de 01b.1 impide bajar de la categoría de 90).
    putParametro('puntos_por_colaborador', 90)->assertOk();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['nombre' => 'Nueva categoría', 'puntuacion_base' => 95]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'La puntuación base debe estar entre 1 y 90.');
});

it('rechaza editar solo otro campo si la puntuación ya supera el máximo y deja la categoría igual', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 150, 'nombre' => 'Original', 'estado' => 'Inactivo']);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['nombre' => 'Renombrada']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'La puntuación base debe estar entre 1 y 100.');

    expect($categoria->fresh()->nombre)->toBe('Original')
        ->and($categoria->fresh()->puntuacion_base)->toBe(150);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['nombre' => 'Renombrada', 'puntuacion_base' => 100]))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Renombrada');
});

it('guardar sin cambios con la puntuación dentro del tope no altera la fecha ni la auditoría', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 80]);
    $antes = $categoria->fresh()->getRawOriginal('updated_at');

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria))->assertOk();

    expect($categoria->fresh()->getRawOriginal('updated_at'))->toBe($antes);
    $this->assertDatabaseCount('auditoria_categorias_reconocimiento', 0);
});

it('responde 409 por versión desactualizada antes que 422 por puntuación', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 50]);
    $viejo = cuerpoDeCategoria($categoria, ['puntuacion_base' => 500]);
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['puntuacion_base' => 60]))->assertOk();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", $viejo)->assertStatus(409);
});
