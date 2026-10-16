<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('calcula la cobertura sobre las competencias activas con recursos disponibles', function () {
    $competencias = CompetenciaModel::factory()->count(10)->create();
    foreach ($competencias->take(4) as $c) {
        configurarFormativa($c->id, null, [CursoModel::factory()->create()->id]);
    }
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo/cobertura')->assertOk()->assertJson(['data' => [
        'activas_totales' => 10,
        'activas_cubiertas' => 4,
        'porcentaje' => 40,
    ]]);
});

it('una activa con solo recursos archivados no cuenta como cubierta', function () {
    $c = CompetenciaModel::factory()->create();
    configurarFormativa($c->id, null, [CursoModel::factory()->archivado()->create()->id]);
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo/cobertura')->assertJsonPath('data.activas_cubiertas', 0)->assertJsonPath('data.porcentaje', 0);
});

it('las inactivas no cuentan aunque conserven vínculos', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    CompetenciaModel::factory()->create();
    configurarFormativa($inactiva->id, null, [CursoModel::factory()->create()->id]);
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo/cobertura')->assertJsonPath('data.activas_totales', 1)->assertJsonPath('data.activas_cubiertas', 0);
});

it('sin competencias activas devuelve 0 %', function () {
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo/cobertura')->assertOk()->assertJsonPath('data.porcentaje', 0)->assertJsonPath('data.activas_totales', 0);
});

it('el colaborador recibe 403 y sin sesión 401', function () {
    $this->getJson('/api/catalogo-formativo/cobertura')->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson('/api/catalogo-formativo/cobertura')->assertForbidden();
});
