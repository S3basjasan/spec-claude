<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CategoriaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEscalaHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('cambia solo los nombres de los grados, sobre las mismas filas', function () {
    $competencia = CompetenciaModel::factory()->create();
    $gradosAntes = CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->orderBy('id')->get(['id', 'descripcion'])->toArray();
    $comportamientosAntes = ComportamientoModel::query()->orderBy('id')->pluck('descripcion', 'id')->all();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])
        ->assertOk()
        ->assertJsonPath('data.escala.nombre', 'Números')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.grados.*.etiqueta', ['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4']);

    expect(CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->orderBy('id')->get(['id', 'descripcion'])->toArray())->toBe($gradosAntes)
        ->and(ComportamientoModel::query()->orderBy('id')->pluck('descripcion', 'id')->all())->toBe($comportamientosAntes);
});

it('registra el cambio de escala en el historial con el usuario', function () {
    $competencia = CompetenciaModel::factory()->create();
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])->assertOk();

    $fila = CompetenciaEscalaHistorialModel::query()->where('competencia_id', $competencia->id)->first();
    expect([$fila->escala_anterior, $fila->escala_nueva, $fila->user_id])->toBe(['Letras', 'Números', $admin->id])
        ->and($competencia->fresh()->updated_by)->toBe($admin->id);
});

it('rechaza con 422 la escala que ya tiene o que no existe', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Letras')])
        ->assertUnprocessable()->assertJsonValidationErrors('tipo_grado_id');
    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => 99999])
        ->assertUnprocessable()->assertJsonValidationErrors('tipo_grado_id');
    $this->patchJson("/api/competencias/{$competencia->id}/escala", [])->assertUnprocessable();

    expect(CompetenciaEscalaHistorialModel::query()->count())->toBe(0)
        ->and($competencia->fresh()->version)->toBe(1);
});

it('conserva los nombres propios de las categorías al ir a otra escala y volver', function () {
    $competencia = CompetenciaModel::factory()->conCategorias(['Maestría', 'Avanzado', 'Competente', 'Inicial'])->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Letras')])
        ->assertOk()->assertJsonPath('data.grados.0.etiqueta', 'Grado A');

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Categorías')])
        ->assertOk()
        ->assertJsonPath('data.grados.*.etiqueta', ['Maestría', 'Avanzado', 'Competente', 'Inicial']);
});

it('usa los nombres por defecto al aplicar Categorías si la competencia no tiene nombres propios', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Categorías')])
        ->assertOk()
        ->assertJsonPath('data.categorias', null)
        ->assertJsonPath('data.grados.*.etiqueta', ['Excelente', 'Muy buena', 'Buena', 'Normal']);

    expect(CategoriaModel::query()->count())->toBe(0);
});

it('no altera las asignaciones de los puestos', function () {
    $competencia = CompetenciaModel::factory()->create();
    $puesto = puestoDe();
    PuestoCompetenciaModel::query()->create(['puesto_id' => $puesto->id, 'competencia_id' => $competencia->id, 'grado_requerido' => 'B']);
    $antes = PuestoCompetenciaModel::query()->first()->competencia_grado_id;
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])->assertOk();

    $asignacion = PuestoCompetenciaModel::query()->with('grado.valor')->first();
    expect($asignacion->competencia_grado_id)->toBe($antes)
        ->and($asignacion->grado_requerido)->toBe('B');
});

it('permite aplicar la escala a una competencia inactiva', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$inactiva->id}/escala", ['tipo_grado_id' => escalaId('Números')])
        ->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('rechaza con 401, 403 y 404', function () {
    $competencia = CompetenciaModel::factory()->create();

    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])->assertForbidden();

    actuarComoAdmin();
    $this->patchJson('/api/competencias/99999/escala', ['tipo_grado_id' => escalaId('Números')])->assertNotFound();
});
