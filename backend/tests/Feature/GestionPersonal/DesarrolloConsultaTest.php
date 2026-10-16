<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;

it('una ficha sin datos devuelve listas vacías y movilidad no informada (GP03-CA01)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $respuesta = $this->getJson("/api/colaboradores/{$ficha->id}/desarrollo")->assertOk();

    expect($respuesta->json('data.colaborador_id'))->toBe($ficha->id)
        ->and($respuesta->json('data.formaciones'))->toBe([])
        ->and($respuesta->json('data.experiencias'))->toBe([])
        ->and($respuesta->json('data.movilidad'))->toBe(['estado' => 'NoInformada', 'observaciones' => null, 'actualizada_at' => null, 'version' => 1])
        ->and($respuesta->json('data.acciones.editar'))->toBeTrue()
        ->and($respuesta->json('data.hoy'))->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('exige sesión y rechaza al colaborador', function () {
    $ficha = fichaDe();
    $this->getJson("/api/colaboradores/{$ficha->id}/desarrollo")->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson("/api/colaboradores/{$ficha->id}/desarrollo")->assertForbidden();
});

it('responde 404 idéntico para ficha inexistente y de otro tenant', function () {
    actuarComoAdminDeTenant();
    $ajena = fichaDe([], 2);

    $a = $this->getJson('/api/colaboradores/999999/desarrollo')->assertNotFound();
    $b = $this->getJson("/api/colaboradores/{$ajena->id}/desarrollo")->assertNotFound();

    expect($b->json())->toBe($a->json())->and($a->json('code'))->toBe('FICHA_NO_ENCONTRADA');
});

it('ordena experiencia por inicio descendente y formación con fechas nulas al final', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $f = ['tenant_id' => 1, 'colaborador_id' => $ficha->id];

    FormacionAcademicaModel::factory()->create($f + ['titulo_programa' => 'Sin fecha']);
    FormacionAcademicaModel::factory()->create($f + ['titulo_programa' => 'Antigua', 'fecha_inicio' => '2010-01-01']);
    FormacionAcademicaModel::factory()->create($f + ['titulo_programa' => 'Reciente', 'fecha_inicio' => '2018-01-01']);
    ExperienciaAnteriorModel::factory()->create($f + ['organizacion' => 'Vieja', 'fecha_inicio' => '2012-01-01']);
    ExperienciaAnteriorModel::factory()->create($f + ['organizacion' => 'Nueva', 'fecha_inicio' => '2020-01-01']);

    $datos = $this->getJson("/api/colaboradores/{$ficha->id}/desarrollo")->assertOk()->json('data');

    expect(array_column($datos['formaciones'], 'titulo_programa'))->toBe(['Reciente', 'Antigua', 'Sin fecha'])
        ->and(array_column($datos['experiencias'], 'organizacion'))->toBe(['Nueva', 'Vieja']);
});

it('la capacidad desarrollo queda instalada en la ficha', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();

    $this->getJson("/api/colaboradores/{$ficha->id}")->assertOk()->assertJsonPath('data.capacidades.desarrollo', true);
});
