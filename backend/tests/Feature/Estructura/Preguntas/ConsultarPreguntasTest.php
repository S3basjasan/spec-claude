<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('el listado devuelve datos de la competencia sin preguntas, activas por defecto, A–Z', function () {
    actuarComoAdmin();
    registroPreguntasDe(['nombre' => 'Zeta']);
    registroPreguntasDe(['nombre' => 'Alfa']);
    registroPreguntasDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);
    CompetenciaModel::factory()->create(['nombre' => 'Sin registro']);

    $respuesta = $this->getJson('/api/preguntas')->assertOk()
        ->assertJsonPath('data.*.nombre', ['Alfa', 'Zeta'])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data' => [['competencia_id', 'codigo', 'nombre', 'descripcion', 'tipo', 'estado']], 'links', 'meta']);

    expect($respuesta->json('data.0'))->not->toHaveKey('preguntas');
});

it('el administrador filtra por estado: Inactivo y Todos', function () {
    actuarComoAdmin();
    registroPreguntasDe(['nombre' => 'Activa']);
    registroPreguntasDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);

    $this->getJson('/api/preguntas?estado=Inactivo')->assertJsonPath('data.*.nombre', ['Retirada']);
    $this->getJson('/api/preguntas?estado=Todos')->assertJsonPath('meta.total', 2);
});

it('un usuario solo ve activas aunque envíe otro estado', function () {
    actuarComoUsuario();
    registroPreguntasDe(['nombre' => 'Activa']);
    registroPreguntasDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);

    $this->getJson('/api/preguntas?estado=Inactivo')->assertOk()->assertJsonPath('data.*.nombre', ['Activa']);
    $this->getJson('/api/preguntas?estado=Todos')->assertJsonPath('meta.total', 1);
});

it('busca por nombre sin distinguir mayúsculas, trata % y _ como literales y filtra por tipo', function () {
    actuarComoAdmin();
    registroPreguntasDe(['nombre' => 'Orientación a resultados', 'tipo' => 'Cardinal']);
    registroPreguntasDe(['nombre' => 'Gestión 100% digital', 'tipo' => 'Especifica']);
    registroPreguntasDe(['nombre' => 'Gestión de proyectos', 'tipo' => 'Especifica']);

    $this->getJson('/api/preguntas?q=ORIENTACIÓN')->assertJsonPath('data.*.nombre', ['Orientación a resultados']);
    $this->getJson('/api/preguntas?q=100%25')->assertJsonPath('data.*.nombre', ['Gestión 100% digital']);
    $this->getJson('/api/preguntas?q=_')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/preguntas?tipo=Especifica')->assertJsonPath('meta.total', 2);
});

it('valida los parámetros del listado y pagina', function () {
    actuarComoAdmin();
    foreach (range(1, 3) as $i) {
        registroPreguntasDe(['nombre' => "Comp $i"]);
    }

    $this->getJson('/api/preguntas?tipo=Otro')->assertUnprocessable();
    $this->getJson('/api/preguntas?estado=Otro')->assertUnprocessable();
    $this->getJson('/api/preguntas?per_page=51')->assertUnprocessable();
    $this->getJson('/api/preguntas?per_page=2&page=2')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
});

it('el detalle devuelve las cuatro preguntas en orden, también de una competencia inactiva y para un Usuario', function () {
    actuarComoUsuario();
    $competencia = registroPreguntasDe(['estado' => 'Inactivo']);

    $respuesta = $this->getJson("/api/preguntas/{$competencia->id}")->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.version', 1)
        ->assertJsonCount(4, 'data.preguntas');

    expect($respuesta->json('data.preguntas.0'))->toBe("Pregunta 1 de la competencia {$competencia->id}")
        ->and($respuesta->json('data.preguntas.3'))->toBe("Pregunta 4 de la competencia {$competencia->id}");
});

it('el detalle devuelve 404 sin registro y 401 sin sesión', function () {
    $competencia = CompetenciaModel::factory()->create();
    $this->getJson("/api/preguntas/{$competencia->id}")->assertUnauthorized();

    actuarComoAdmin();
    $this->getJson("/api/preguntas/{$competencia->id}")
        ->assertNotFound()->assertExactJson(['message' => 'El registro de preguntas no existe.']);
    $this->getJson('/api/preguntas/999')->assertNotFound();
});

it('el listado exige sesión', function () {
    $this->getJson('/api/preguntas')->assertUnauthorized();
});

it('los datos de la competencia se reflejan al instante y no hay DELETE ni cambio de estado', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe(['nombre' => 'Antes']);
    $competencia->forceFill(['nombre' => 'Después', 'nombre_clave' => 'después'])->save();

    $this->getJson("/api/preguntas/{$competencia->id}")->assertJsonPath('data.nombre', 'Después');
    $this->deleteJson("/api/preguntas/{$competencia->id}")->assertStatus(405);
    $this->patchJson("/api/preguntas/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertNotFound();
});
