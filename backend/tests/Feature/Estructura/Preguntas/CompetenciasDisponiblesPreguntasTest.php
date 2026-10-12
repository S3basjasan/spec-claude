<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('lista solo competencias activas sin registro, ordenadas por nombre', function () {
    actuarComoAdmin();
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    registroPreguntasDe(['nombre' => 'Con registro']);

    $this->getJson('/api/preguntas/competencias-disponibles')
        ->assertOk()
        ->assertJsonPath('data.*.nombre', ['Alfa', 'Zeta'])
        ->assertJsonStructure(['data' => [['competencia_id', 'codigo', 'nombre', 'descripcion', 'tipo']]]);
});

it('una competencia desaparece de disponibles al crearle su registro', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();

    $this->getJson('/api/preguntas/competencias-disponibles')->assertJsonCount(1, 'data');
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertCreated();
    $this->getJson('/api/preguntas/competencias-disponibles')->assertJsonCount(0, 'data');
});

it('exige sesión y rol Administrador', function () {
    $this->getJson('/api/preguntas/competencias-disponibles')->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson('/api/preguntas/competencias-disponibles')->assertForbidden();
});
