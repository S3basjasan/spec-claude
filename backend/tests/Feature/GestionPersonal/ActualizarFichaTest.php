<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;

function fichaCreadaPorApi(mixed $prueba, array $cambios = []): array
{
    return crearFichaApi($prueba, datosFicha($cambios))->assertCreated()->json('data');
}

it('corrige la identificación conservando id, código y asociaciones (GP01-CA03)', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this);
    $cuenta = UserModel::factory()->create();
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => 1, 'user_id' => $cuenta->id, 'colaborador_id' => $ficha['id'], 'puede_consultar_ficha' => true,
    ]);

    $respuesta = $this->patchJson('/api/colaboradores/'.$ficha['id'], [
        'version' => $ficha['version'], 'numero_identificacion' => '0999999999', 'nombres' => 'Ana María',
    ])->assertOk();

    $modelo = ColaboradorModel::query()->findOrFail($ficha['id']);

    expect($respuesta->json('data.id'))->toBe($ficha['id'])
        ->and($respuesta->json('data.codigo'))->toBe($ficha['codigo'])
        ->and($respuesta->json('data.version'))->toBe(2)
        ->and($respuesta->json('data.numero_identificacion'))->toBe('0999999999')
        ->and($modelo->identificacion_clave)->toBe('0999999999')
        ->and($modelo->nombres_busqueda)->toBe('ana maria')
        ->and($modelo->updated_by)->toBe($admin->id)
        ->and(ColaboradorCuentaModel::query()->where('colaborador_id', $ficha['id'])->count())->toBe(1);
});

it('rechaza una identificación ocupada sin modificar nada', function () {
    actuarComoAdminDeTenant();
    $a = fichaCreadaPorApi($this, ['numero_identificacion' => '111']);
    $b = fichaCreadaPorApi($this, ['numero_identificacion' => '222']);

    $this->patchJson('/api/colaboradores/'.$b['id'], ['version' => $b['version'], 'numero_identificacion' => ' 111 ', 'nombres' => 'Cambio'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'IDENTIFICACION_DUPLICADA')
        ->assertJsonPath('ficha_existente.id', $a['id']);

    $modelo = ColaboradorModel::query()->findOrFail($b['id']);
    expect($modelo->numero_identificacion)->toBe('222')->and($modelo->nombres)->toBe('Ana')->and($modelo->version)->toBe(1);
});

it('conservar la propia identificación o cambiar solo sus mayúsculas no es duplicado', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this, ['tipo_identificacion' => 'Pasaporte', 'numero_identificacion' => 'ab123']);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'numero_identificacion' => 'AB123'])
        ->assertOk()->assertJsonPath('data.numero_identificacion', 'AB123');
});

it('una versión obsoleta devuelve 409 y conserva la primera escritura (GP01-CA06)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'nombres' => 'Primera'])->assertOk();
    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'nombres' => 'Segunda'])
        ->assertStatus(409)->assertJsonPath('code', 'FICHA_MODIFICADA');

    expect(ColaboradorModel::query()->findOrFail($ficha['id'])->nombres)->toBe('Primera');
});

it('guardar sin cambios reales no altera versión ni última modificación', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this);
    $antes = ColaboradorModel::query()->findOrFail($ficha['id']);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'nombres' => ' Ana '])
        ->assertOk()->assertJsonPath('data.version', 1);

    $despues = ColaboradorModel::query()->findOrFail($ficha['id']);
    expect($despues->version)->toBe(1)->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});

it('los campos omitidos no cambian, null vacía opcionales y los obligatorios no admiten null', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this, ['nacionalidad' => 'Ecuatoriana', 'telefono' => '0999999999']);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'nacionalidad' => null])
        ->assertOk()
        ->assertJsonPath('data.nacionalidad', null)
        ->assertJsonPath('data.telefono', '0999999999');

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 2, 'nombres' => null])
        ->assertUnprocessable()->assertJsonValidationErrors(['nombres']);
});

it('valida el contacto de emergencia sobre el conjunto resultante', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this, [
        'emergencia_nombre' => 'Rosa', 'emergencia_telefono' => '0999999999', 'emergencia_relacion' => 'Madre',
    ]);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'emergencia_telefono' => null])
        ->assertUnprocessable()->assertJsonValidationErrors(['emergencia_telefono']);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], [
        'version' => 1, 'emergencia_nombre' => null, 'emergencia_telefono' => null, 'emergencia_relacion' => null,
    ])->assertOk()->assertJsonPath('data.emergencia_nombre', null);
});

it('rechaza campos no asignables y exige version', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'codigo' => 'EMP-900'])
        ->assertUnprocessable()->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'tenant_id' => 2])
        ->assertUnprocessable()->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['nombres' => 'Sin versión'])
        ->assertUnprocessable()->assertJsonValidationErrors(['version']);
});

it('no edita fichas de otro tenant y deniega al Usuario', function () {
    $ajena = fichaDe(['numero_identificacion' => '300'], 2);
    actuarComoAdminDeTenant();

    $this->patchJson('/api/colaboradores/'.$ajena->id, ['version' => 1, 'nombres' => 'Intruso'])->assertNotFound();
    expect($ajena->fresh()->nombres)->not->toBe('Intruso');

    actuarComoUsuarioDeTenant();
    $this->patchJson('/api/colaboradores/'.$ajena->id, ['version' => 1, 'nombres' => 'Intruso'])->assertForbidden();
});

it('con la escritura deshabilitada la edición responde 503', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this);
    config(['hrms.gestion_personal.escritura_habilitada' => false]);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'nombres' => 'X'])
        ->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');
    $this->getJson('/api/colaboradores/'.$ficha['id'])->assertOk();
});

it('PATCH de la ficha rechaza las columnas de movilidad y no las expone en los datos (GP-03)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaCreadaPorApi($this);

    $this->patchJson('/api/colaboradores/'.$ficha['id'], ['version' => 1, 'movilidad_estado' => 'Disponible'])->assertUnprocessable();

    $detalle = $this->getJson('/api/colaboradores/'.$ficha['id'])->assertOk()->json('data');
    expect(array_keys($detalle))->not->toContain('movilidad_estado')->not->toContain('movilidad_version');
    expect(ColaboradorModel::query()->find($ficha['id'])->movilidad_estado)->toBe('NoInformada');
});
