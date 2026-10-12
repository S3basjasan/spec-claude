<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Support\Facades\Route;

it('no existe ningún endpoint DELETE para las competencias', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $respuesta = $this->deleteJson("/api/competencias/{$competencia->id}");

    expect($respuesta->getStatusCode())->toBeIn([404, 405])
        ->and(CompetenciaModel::query()->count())->toBe(1);
});

it('ninguna ruta de competencias admite DELETE', function () {
    $metodos = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'api/competencias'))
        ->flatMap(fn ($ruta) => $ruta->methods())
        ->unique()
        ->values()
        ->all();

    expect($metodos)->not->toContain('DELETE')
        ->and($metodos)->toContain('PATCH');
});

it('el Usuario tampoco puede borrar y recibe el mismo resultado', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoUsuario();

    expect($this->deleteJson("/api/competencias/{$competencia->id}")->getStatusCode())->toBeIn([404, 405]);
});
