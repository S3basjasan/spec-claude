<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('no existe ninguna ruta para eliminar una competencia', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->deleteJson("/api/competencias/{$competencia->id}")->assertStatus(405);

    expect(CompetenciaModel::query()->count())->toBe(1);
});

it('ya no existen las rutas separadas de comportamientos y preguntas', function () {
    actuarComoAdmin();

    foreach (['/api/comportamientos', '/api/preguntas', '/api/comportamientos/competencias-disponibles', '/api/preguntas/competencias-disponibles'] as $ruta) {
        $this->getJson($ruta)->assertNotFound();
    }
});

it('la base impide borrar una competencia que tiene contenido', function () {
    $competencia = CompetenciaModel::factory()->create();

    expect(fn () => DB::table('competencias')->where('id', $competencia->id)->delete())->toThrow(QueryException::class);
});
