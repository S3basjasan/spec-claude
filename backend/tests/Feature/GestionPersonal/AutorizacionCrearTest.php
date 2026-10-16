<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;

it('exige sesión y deniega al Usuario al crear (GP01-CA04)', function () {
    $this->postJson('/api/colaboradores', datosFicha())->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    crearFichaApi($this, datosFicha())->assertForbidden();

    expect(ColaboradorModel::query()->count())->toBe(0);
});

it('rechaza los campos no asignables, incluido el tenant del cuerpo', function () {
    actuarComoAdminDeTenant();

    foreach (['tenant_id' => 2, 'codigo' => 'EMP-777', 'id' => 5, 'version' => 3, 'estado' => 'Activo',
        'puesto' => 'Jefe', 'identificacion_clave' => 'x', 'created_by' => 9] as $campo => $valor) {
        crearFichaApi($this, datosFicha([$campo => $valor]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'CAMPO_NO_PERMITIDO')
            ->assertJsonValidationErrors([$campo]);
    }

    expect(ColaboradorModel::query()->count())->toBe(0);
});

it('crea siempre en el tenant de la cuenta', function () {
    actuarComoAdminDeTenant(2);

    crearFichaApi($this, datosFicha())->assertCreated();

    expect(ColaboradorModel::query()->firstOrFail()->tenant_id)->toBe(2);
});

it('con la escritura deshabilitada responde 503 y no crea nada', function () {
    config(['hrms.gestion_personal.escritura_habilitada' => false]);
    actuarComoAdminDeTenant();

    crearFichaApi($this, datosFicha())
        ->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');

    expect(ColaboradorModel::query()->count())->toBe(0);
});
