<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;

beforeEach(function () {
    fichaDe(['nombres' => 'Ana', 'apellidos' => 'Núñez Paredes', 'numero_identificacion' => '1', 'codigo' => 'EMP-001']);
    fichaDe(['nombres' => 'Luis', 'apellidos' => 'Núñez Andrade', 'numero_identificacion' => '2', 'codigo' => 'EMP-002']);
    fichaDe(['nombres' => 'María', 'apellidos' => 'Pérez Soto', 'numero_identificacion' => '3', 'codigo' => 'EMP-003']);
    fichaDe(['nombres' => 'Rosa', 'apellidos' => 'Chávez Díaz', 'numero_identificacion' => '1', 'codigo' => 'EMP-004'], 2);
});

it('lista solo las fichas del tenant ordenadas por apellidos, nombres y código', function () {
    actuarComoAdminDeTenant();

    $respuesta = $this->getJson('/api/colaboradores')->assertOk();

    expect(array_column($respuesta->json('data'), 'codigo'))->toBe(['EMP-002', 'EMP-001', 'EMP-003'])
        ->and($respuesta->json('meta.per_page'))->toBe(20)
        ->and($respuesta->json('meta.total'))->toBe(3)
        ->and($respuesta->json('data.0'))->toBe([
            'id' => $respuesta->json('data.0.id'), 'codigo' => 'EMP-002', 'nombres' => 'Luis', 'apellidos' => 'Núñez Andrade',
            'estado' => 'SinVinculoVigente', 'puesto' => null, 'unidad' => null, 'laboral_disponible' => true,
        ]);
});

it('busca sin distinguir mayúsculas ni tildes por nombre, apellido y código', function () {
    actuarComoAdminDeTenant();

    foreach (['nunez' => 2, 'NÚÑEZ' => 2, 'maria' => 1, 'ana nunez' => 1, 'emp-003' => 1, 'perez soto' => 1, 'zzz' => 0] as $q => $cantidad) {
        $this->getJson('/api/colaboradores?q='.urlencode($q))->assertOk()->assertJsonPath('meta.total', $cantidad);
    }
});

it('trata % y _ de la búsqueda como texto literal', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/colaboradores?q='.urlencode('%'))->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/colaboradores?q='.urlencode('_'))->assertOk()->assertJsonPath('meta.total', 0);
});

it('pagina y combina búsqueda con página', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/colaboradores?per_page=2&page=2')->assertOk()
        ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(1, 'data');
    $this->getJson('/api/colaboradores?q=nunez&per_page=1&page=2')->assertOk()
        ->assertJsonPath('data.0.codigo', 'EMP-001');
});

it('filtra por estado y valida filtros no disponibles', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/colaboradores?estado=SinVinculoVigente')->assertOk()->assertJsonPath('meta.total', 3);
    $this->getJson('/api/colaboradores?estado=Todos')->assertOk()->assertJsonPath('meta.total', 3);
    $this->getJson('/api/colaboradores?estado=Activo')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/colaboradores?estado=Inventado')->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->getJson('/api/colaboradores?unidad_id=1')->assertUnprocessable()->assertJsonValidationErrors(['unidad_id']);
    $this->getJson('/api/colaboradores?per_page=51')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
});

it('ofrece Todos y solo los estados que alguna ficha tiene', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/colaboradores/filtros')->assertOk()
        ->assertJsonPath('data.estados', [['valor' => 'Todos'], ['valor' => 'SinVinculoVigente']])
        ->assertJsonPath('data.unidades', []);

    actuarComoAdminDeTenant(2);
    ColaboradorModel::query()->where('tenant_id', 2)->delete();
    $this->getJson('/api/colaboradores/filtros')->assertOk()
        ->assertJsonPath('data.estados', [['valor' => 'Todos']]);
});

it('el detalle muestra los opcionales ausentes como null y conserva 0 hijos (GP01-CA05)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '55', 'numero_hijos' => 0]);

    $detalle = $this->getJson('/api/colaboradores/'.$ficha->id)->assertOk();

    expect($detalle->json('data.numero_hijos'))->toBe(0)
        ->and($detalle->json('data.nacionalidad'))->toBeNull()
        ->and($detalle->json('data.fecha_nacimiento'))->toBeNull()
        ->and($detalle->json('data.laboral'))->toBe(['disponibilidad' => 'disponible', 'estado' => 'SinVinculoVigente', 'puesto' => null, 'unidad' => null, 'proximo_ingreso' => null])
        ->and($detalle->json('data.trayectoria'))->toBe(['disponibilidad' => 'disponible', 'hechos' => []])
        ->and($detalle->json('data'))->not->toHaveKeys(['salario', 'cuenta_bancaria', 'adecuacion', 'potencial', 'tenant_id', 'identificacion_clave']);
});

it('una ficha de otro tenant o inexistente da el mismo 404 (GP01-CA04)', function () {
    actuarComoAdminDeTenant();
    $ajena = fichaDe(['numero_identificacion' => '77'], 2);

    $a = $this->getJson('/api/colaboradores/'.$ajena->id)->assertNotFound();
    $b = $this->getJson('/api/colaboradores/999999')->assertNotFound();

    expect($a->json())->toBe($b->json())
        ->and($a->json('code'))->toBe('FICHA_NO_ENCONTRADA');
});

it('deniega el listado y el detalle al Usuario y sin sesión', function () {
    $this->getJson('/api/colaboradores')->assertUnauthorized();
    $ficha = fichaDe(['numero_identificacion' => '88']);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/colaboradores')->assertForbidden();
    $this->getJson('/api/colaboradores/filtros')->assertForbidden();
    $this->getJson('/api/colaboradores/'.$ficha->id)->assertForbidden();
});

it('no expone DELETE', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '99']);

    expect($this->deleteJson('/api/colaboradores/'.$ficha->id)->status())->toBeIn([404, 405]);
});
