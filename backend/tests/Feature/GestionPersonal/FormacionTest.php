<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;

function cuerpoFormacion(array $cambios = []): array
{
    return array_merge([
        'nivel' => 'Tercer nivel', 'titulo_programa' => 'Ingeniería de Sistemas', 'institucion' => 'EPN', 'estado' => 'Completada',
    ], $cambios);
}

it('guarda una formación válida sin fechas (GP03-CA02)', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $respuesta = $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion())->assertCreated();

    expect($respuesta->json('data.fecha_inicio'))->toBeNull()
        ->and($respuesta->json('data.fecha_finalizacion'))->toBeNull()
        ->and($respuesta->json('data.version'))->toBe(1);
    $fila = FormacionAcademicaModel::query()->first();
    expect($fila->colaborador_id)->toBe($ficha->id)->and($fila->tenant_id)->toBe(1)->and($fila->created_by)->toBe($admin->id);
});

it('rechaza campos obligatorios faltantes o de solo espacios', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", [])->assertUnprocessable()
        ->assertJsonValidationErrors(['nivel', 'titulo_programa', 'institucion', 'estado']);
    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion(['nivel' => '   ']))->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDACION')->assertJsonValidationErrors(['nivel']);
    expect(FormacionAcademicaModel::query()->count())->toBe(0);
});

it('aplica los límites 100/200/200/2000', function (string $campo, int $max) {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion([$campo => str_repeat('a', $max)]))->assertCreated();
    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion([$campo => str_repeat('a', $max + 1)]))
        ->assertUnprocessable()->assertJsonValidationErrors([$campo]);
})->with([['nivel', 100], ['titulo_programa', 200], ['institucion', 200], ['observaciones', 2000]]);

it('rechaza fin anterior al inicio sin alterar otras filas (GP03-CA03)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $otra = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id]);

    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion(['fecha_inicio' => '2020-05-01', 'fecha_finalizacion' => '2020-04-01']))
        ->assertUnprocessable()->assertJsonValidationErrors(['fecha_finalizacion']);

    expect(FormacionAcademicaModel::query()->count())->toBe(1)->and($otra->fresh()->version)->toBe(1);
});

it('acepta inicio igual a fin, estado sin fechas y fin futura en curso; rechaza inicio futuro', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $url = "/api/colaboradores/{$ficha->id}/formaciones";

    $this->postJson($url, cuerpoFormacion(['fecha_inicio' => '2020-01-01', 'fecha_finalizacion' => '2020-01-01']))->assertCreated();
    $this->postJson($url, cuerpoFormacion(['estado' => 'Completada']))->assertCreated();
    $this->postJson($url, cuerpoFormacion(['estado' => 'EnCurso', 'fecha_finalizacion' => hoyDelTenant(400)]))->assertCreated();
    $this->postJson($url, cuerpoFormacion(['fecha_inicio' => hoyDelTenant(2)]))->assertUnprocessable()->assertJsonValidationErrors(['fecha_inicio']);
});

it('acepta filas idénticas', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion())->assertCreated();
    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion())->assertCreated();

    expect(FormacionAcademicaModel::query()->count())->toBe(2);
});

it('corrige parcialmente validando el estado final y cambia solo esa fila', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $a = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id, 'fecha_inicio' => '2020-06-01']);
    $b = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id]);

    $this->patchJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}", ['version' => 1, 'fecha_finalizacion' => '2020-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors(['fecha_finalizacion']);

    $respuesta = $this->patchJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}", ['version' => 1, 'institucion' => 'Nueva', 'observaciones' => 'ok'])->assertOk();

    expect($respuesta->json('data.institucion'))->toBe('Nueva')->and($respuesta->json('data.version'))->toBe(2)
        ->and($a->fresh()->updated_by)->toBe($admin->id)
        ->and($b->fresh()->version)->toBe(1);

    $this->patchJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}", ['version' => 2, 'observaciones' => null])->assertOk();
    expect($a->fresh()->observaciones)->toBeNull();
});

it('sin cambios reales conserva versión y fecha de actualización', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $a = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id, 'updated_at' => '2024-01-01 10:00:00']);

    $respuesta = $this->patchJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}", ['version' => 1, 'nivel' => 'Tercer nivel'])->assertOk();

    expect($respuesta->json('data.version'))->toBe(1)
        ->and($a->fresh()->version)->toBe(1)
        ->and($a->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
});

it('responde 409 con versión vieja, 404 cruzado y exige version', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $otra = fichaDe();
    $ajena = fichaDe([], 2);
    $a = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id, 'version' => 3]);
    $deAjena = FormacionAcademicaModel::factory()->create(['tenant_id' => 2, 'colaborador_id' => $ajena->id]);

    $this->patchJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}", ['version' => 1, 'nivel' => 'X'])
        ->assertStatus(409)->assertJsonPath('code', 'REGISTRO_MODIFICADO');
    $this->patchJson("/api/colaboradores/{$otra->id}/formaciones/{$a->id}", ['version' => 3, 'nivel' => 'X'])
        ->assertNotFound()->assertJsonPath('code', 'REGISTRO_NO_ENCONTRADO');
    $this->patchJson("/api/colaboradores/{$ajena->id}/formaciones/{$deAjena->id}", ['version' => 1, 'nivel' => 'X'])->assertNotFound();
    $this->patchJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}", ['nivel' => 'X'])->assertUnprocessable()->assertJsonValidationErrors(['version']);
    expect($a->fresh()->nivel)->toBe('Tercer nivel');
});

it('ignora colaborador_id, tenant_id y created_by del cuerpo', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $otra = fichaDe();

    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion(['colaborador_id' => $otra->id, 'tenant_id' => 2, 'created_by' => 999]))->assertCreated();

    $fila = FormacionAcademicaModel::query()->first();
    expect($fila->colaborador_id)->toBe($ficha->id)->and($fila->tenant_id)->toBe(1)->and($fila->created_by)->not->toBe(999);
});

it('no existe eliminación', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $a = FormacionAcademicaModel::factory()->create(['colaborador_id' => $ficha->id]);

    expect($this->deleteJson("/api/colaboradores/{$ficha->id}/formaciones/{$a->id}")->status())->toBeIn([404, 405])
        ->and(FormacionAcademicaModel::query()->count())->toBe(1);
});

it('rechaza al colaborador y respeta la escritura deshabilitada', function () {
    $ficha = fichaDe();
    actuarComoUsuarioDeTenant();
    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion())->assertForbidden();

    actuarComoAdminDeTenant();
    config(['hrms.gestion_personal.escritura_habilitada' => false]);
    $this->postJson("/api/colaboradores/{$ficha->id}/formaciones", cuerpoFormacion())->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');
});
