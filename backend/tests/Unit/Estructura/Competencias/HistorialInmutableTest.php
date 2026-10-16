<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

function registroDeHistorial(): CompetenciaEstadoHistorialModel
{
    $competencia = CompetenciaModel::factory()->create();

    return CompetenciaEstadoHistorialModel::query()->create([
        'competencia_id' => $competencia->id,
        'estado_anterior' => 'Activo',
        'estado_nuevo' => 'Inactivo',
        'user_id' => 1,
    ]);
}

uses(TestCase::class, RefreshDatabase::class);

it('no permite modificar un registro del historial', function () {
    $registro = registroDeHistorial();

    $registro->estado_nuevo = 'Activo';
    $registro->save();
})->throws(LogicException::class, 'El historial de estados no se puede modificar.');

it('no permite borrar un registro del historial', function () {
    registroDeHistorial()->delete();
})->throws(LogicException::class, 'El historial de estados no se puede borrar.');

it('no tiene columna de actualización', function () {
    expect(CompetenciaEstadoHistorialModel::UPDATED_AT)->toBeNull()
        ->and(registroDeHistorial()->updated_at)->toBeNull();
});

it('ninguna ruta de la API de competencias expone el historial', function () {
    // Acotada a /api/competencias: otros módulos (p. ej. el catálogo formativo) tienen su propio historial.
    $rutas = collect(Route::getRoutes()->getRoutes())->map(fn ($ruta) => $ruta->uri())
        ->filter(fn ($uri) => str_starts_with($uri, 'api/competencias'))->all();

    expect(array_filter($rutas, fn ($uri) => str_contains($uri, 'historial')))->toBe([]);
});
