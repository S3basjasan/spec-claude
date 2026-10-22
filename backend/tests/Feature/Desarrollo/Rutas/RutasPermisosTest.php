<?php

it('sin sesión responde 401', function () {
    $this->getJson('/api/rutas/permisos')->assertUnauthorized();
});

it('el administrador recibe las cinco claves de gestión y consulta', function () {
    actuarComoAdminDeTenant();

    $permisos = $this->getJson('/api/rutas/permisos')->assertOk()->json('data.permisos');

    expect($permisos)->toBe([
        'desarrollo.rutas.ver',
        'desarrollo.rutas.gestionar',
        'desarrollo.rutas.inscribir',
        'desarrollo.rutas.inscripciones',
        'desarrollo.rutas.historial',
    ]);
});

it('el colaborador recibe solo las claves terminadas en _propio', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/rutas/permisos')->assertOk()->assertJsonPath('data.permisos', ['desarrollo.rutas.ver_propio']);
});
