<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;

function cuerpoExperiencia(array $cambios = []): array
{
    return array_merge(['organizacion' => 'ACME', 'cargo' => 'Analista', 'fecha_inicio' => '2019-01-07'], $cambios);
}

it('exige organización, cargo e inicio', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->postJson("/api/colaboradores/{$ficha->id}/experiencias", [])->assertUnprocessable()
        ->assertJsonValidationErrors(['organizacion', 'cargo', 'fecha_inicio']);
    $this->postJson("/api/colaboradores/{$ficha->id}/experiencias", cuerpoExperiencia())->assertCreated();
});

it('aplica los límites de texto', function (string $campo, int $max) {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $url = "/api/colaboradores/{$ficha->id}/experiencias";

    $this->postJson($url, cuerpoExperiencia([$campo => str_repeat('a', $max)]))->assertCreated();
    $this->postJson($url, cuerpoExperiencia([$campo => str_repeat('a', $max + 1)]))->assertUnprocessable()->assertJsonValidationErrors([$campo]);
})->with([['organizacion', 200], ['cargo', 200], ['funciones', 2000], ['observaciones', 2000]]);

it('rechaza fin anterior al inicio, inicio futuro y fin futura (GP03-CA03)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $url = "/api/colaboradores/{$ficha->id}/experiencias";

    $this->postJson($url, cuerpoExperiencia(['fecha_finalizacion' => '2019-01-01']))->assertUnprocessable()->assertJsonValidationErrors(['fecha_finalizacion']);
    $this->postJson($url, cuerpoExperiencia(['fecha_inicio' => hoyDelTenant(2)]))->assertUnprocessable()->assertJsonValidationErrors(['fecha_inicio']);
    $this->postJson($url, cuerpoExperiencia(['fecha_finalizacion' => hoyDelTenant(2)]))->assertUnprocessable()->assertJsonValidationErrors(['fecha_finalizacion']);
    expect(ExperienciaAnteriorModel::query()->count())->toBe(0);
});

it('sin fin conserva null y no expone campo de vigencia (GP03-CA04)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $datos = $this->postJson("/api/colaboradores/{$ficha->id}/experiencias", cuerpoExperiencia())->assertCreated()->json('data');

    expect($datos['fecha_finalizacion'])->toBeNull()
        ->and(array_keys($datos))->not->toContain('vigente')->not->toContain('actual');
});

it('acepta periodos superpuestos y ordena por inicio e id descendentes de forma estable', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $url = "/api/colaboradores/{$ficha->id}/experiencias";

    $this->postJson($url, cuerpoExperiencia(['organizacion' => 'A', 'fecha_inicio' => '2019-01-01', 'fecha_finalizacion' => '2021-01-01']))->assertCreated();
    $this->postJson($url, cuerpoExperiencia(['organizacion' => 'B', 'fecha_inicio' => '2020-01-01']))->assertCreated();
    $this->postJson($url, cuerpoExperiencia(['organizacion' => 'C', 'fecha_inicio' => '2020-01-01']))->assertCreated();

    $orden = fn () => array_column($this->getJson("/api/colaboradores/{$ficha->id}/desarrollo")->json('data.experiencias'), 'organizacion');

    expect($orden())->toBe(['C', 'B', 'A'])->and($orden())->toBe(['C', 'B', 'A']);
});

it('corrige, detecta 409, 404 cruzado y rechaza al colaborador', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $otra = fichaDe();
    $e = ExperienciaAnteriorModel::factory()->create(['colaborador_id' => $ficha->id]);

    $this->patchJson("/api/colaboradores/{$ficha->id}/experiencias/{$e->id}", ['version' => 1, 'cargo' => 'Líder'])->assertOk()->assertJsonPath('data.version', 2);
    $this->patchJson("/api/colaboradores/{$ficha->id}/experiencias/{$e->id}", ['version' => 1, 'cargo' => 'Otro'])->assertStatus(409);
    $this->patchJson("/api/colaboradores/{$otra->id}/experiencias/{$e->id}", ['version' => 2, 'cargo' => 'Otro'])->assertNotFound();
    $this->patchJson("/api/colaboradores/{$ficha->id}/experiencias/{$e->id}", ['version' => 2, 'fecha_inicio' => null])->assertUnprocessable();

    actuarComoUsuarioDeTenant();
    $this->postJson("/api/colaboradores/{$ficha->id}/experiencias", cuerpoExperiencia())->assertForbidden();
});
