<?php

use App\Modules\Desarrollo\Application\Services\AutorizadorDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoAdecuacion;

it('sin sesión responde 401', function () {
    $this->getJson('/api/adecuaciones/permisos')->assertUnauthorized();
});

it('el administrador recibe las cinco claves de gestión y no ver_propio', function () {
    actuarComoAdminDeTenant();

    $claves = $this->getJson('/api/adecuaciones/permisos')->assertOk()->json('data.claves');

    expect($claves)->toBe([
        'desarrollo.adecuacion.generar',
        'desarrollo.adecuacion.ver',
        'desarrollo.adecuacion.comparar',
        'desarrollo.adecuacion.mapa',
        'desarrollo.adecuacion.parametros',
    ]);
});

it('el colaborador recibe solo ver_propio', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/adecuaciones/permisos')->assertOk()->assertJsonPath('data.claves', ['desarrollo.adecuacion.ver_propio']);
});

it('el colaborador no puede usar ninguna operación de gestión', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/adecuaciones/opciones')->assertForbidden();
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 2])->assertForbidden();
    $this->postJson('/api/adecuaciones', ['colaborador_id' => 10, 'puesto_objetivo_id' => 2])->assertForbidden();
});

it('las operaciones de gestión exigen sesión', function () {
    $this->getJson('/api/adecuaciones/opciones')->assertUnauthorized();
    $this->postJson('/api/adecuaciones/previsualizar', [])->assertUnauthorized();
    $this->postJson('/api/adecuaciones', [])->assertUnauthorized();
});

it('el autorizador reparte las claves por rol', function () {
    $autorizador = new AutorizadorDeAdecuacion;

    foreach (ClaveDePermisoAdecuacion::cases() as $clave) {
        expect($autorizador->permite(true, $clave))->toBe($clave !== ClaveDePermisoAdecuacion::VerPropio)
            ->and($autorizador->permite(false, $clave))->toBe($clave === ClaveDePermisoAdecuacion::VerPropio);
    }
});
