<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('el listado devuelve datos de la competencia sin comportamientos, activas por defecto, A–Z', function () {
    actuarComoAdmin();
    registroDe(['nombre' => 'Zeta']);
    registroDe(['nombre' => 'Alfa']);
    registroDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);
    CompetenciaModel::factory()->create(['nombre' => 'Sin registro']);

    $respuesta = $this->getJson('/api/comportamientos')->assertOk()
        ->assertJsonPath('data.*.nombre', ['Alfa', 'Zeta'])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data' => [['competencia_id', 'codigo', 'nombre', 'descripcion', 'tipo', 'estado']], 'links', 'meta']);

    expect($respuesta->json('data.0'))->not->toHaveKey('comportamientos');
});

it('el administrador filtra por estado: Inactivo y Todos', function () {
    actuarComoAdmin();
    registroDe(['nombre' => 'Activa']);
    registroDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);

    $this->getJson('/api/comportamientos?estado=Inactivo')->assertJsonPath('data.*.nombre', ['Retirada']);
    $this->getJson('/api/comportamientos?estado=Todos')->assertJsonPath('meta.total', 2);
});

it('un usuario solo ve activas aunque envíe otro estado', function () {
    actuarComoUsuario();
    registroDe(['nombre' => 'Activa']);
    registroDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);

    $this->getJson('/api/comportamientos?estado=Inactivo')->assertOk()->assertJsonPath('data.*.nombre', ['Activa']);
    $this->getJson('/api/comportamientos?estado=Todos')->assertJsonPath('meta.total', 1);
});

it('busca por nombre sin distinguir mayúsculas, trata % y _ como literales y filtra por tipo', function () {
    actuarComoAdmin();
    registroDe(['nombre' => 'Orientación a resultados', 'tipo' => 'Cardinal']);
    registroDe(['nombre' => 'Gestión 100% digital', 'tipo' => 'Especifica']);
    registroDe(['nombre' => 'Gestión de proyectos', 'tipo' => 'Especifica']);

    $this->getJson('/api/comportamientos?q=ORIENTACIÓN')->assertJsonPath('data.*.nombre', ['Orientación a resultados']);
    $this->getJson('/api/comportamientos?q=100%25')->assertJsonPath('data.*.nombre', ['Gestión 100% digital']);
    $this->getJson('/api/comportamientos?q=_')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/comportamientos?tipo=Especifica')->assertJsonPath('meta.total', 2);
});

it('valida los parámetros del listado y pagina', function () {
    actuarComoAdmin();
    foreach (range(1, 3) as $i) {
        registroDe(['nombre' => "Comp $i"]);
    }

    $this->getJson('/api/comportamientos?tipo=Otro')->assertUnprocessable();
    $this->getJson('/api/comportamientos?estado=Otro')->assertUnprocessable();
    $this->getJson('/api/comportamientos?per_page=51')->assertUnprocessable();
    $this->getJson('/api/comportamientos?per_page=2&page=2')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
});

it('el detalle devuelve los cuatro grados ordenados, también de una competencia inactiva y para un Usuario', function () {
    actuarComoUsuario();
    $competencia = registroDe(['estado' => 'Inactivo'], ['A' => 3, 'B' => 1, 'C' => 2, 'D' => 4]);

    $respuesta = $this->getJson("/api/comportamientos/{$competencia->id}")->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.version', 1);

    expect(array_keys($respuesta->json('data.comportamientos')))->toBe(['A', 'B', 'C', 'D'])
        ->and($respuesta->json('data.comportamientos.A.0'))->toBe('Comportamiento 1 del grado A')
        ->and($respuesta->json('data.comportamientos.D'))->toHaveCount(4);
});

it('el detalle devuelve 404 sin registro y 401 sin sesión', function () {
    $competencia = CompetenciaModel::factory()->create();
    $this->getJson("/api/comportamientos/{$competencia->id}")->assertUnauthorized();

    actuarComoAdmin();
    $this->getJson("/api/comportamientos/{$competencia->id}")
        ->assertNotFound()->assertExactJson(['message' => 'El registro de comportamientos no existe.']);
    $this->getJson('/api/comportamientos/999')->assertNotFound();
});

it('el listado exige sesión', function () {
    $this->getJson('/api/comportamientos')->assertUnauthorized();
});

it('los datos de la competencia se reflejan al instante y no hay DELETE ni cambio de estado', function () {
    actuarComoAdmin();
    $competencia = registroDe(['nombre' => 'Antes']);
    $competencia->forceFill(['nombre' => 'Después', 'nombre_clave' => 'después'])->save();

    $this->getJson("/api/comportamientos/{$competencia->id}")->assertJsonPath('data.nombre', 'Después');
    $this->deleteJson("/api/comportamientos/{$competencia->id}")->assertStatus(405);
    $this->patchJson("/api/comportamientos/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertNotFound();
});
