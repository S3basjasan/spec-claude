<?php

use App\Modules\Desarrollo\Application\Services\AutorizadorDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoPdi;
use Illuminate\Support\Facades\Route;

it('sin sesión responde 401', function () {
    $this->getJson('/api/pdi/permisos')->assertUnauthorized();
});

it('el administrador recibe las siete claves de gestión y ninguna _propio', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/pdi/permisos')->assertOk()->assertJsonPath('data.claves', [
        'desarrollo.pdi.gestionar',
        'desarrollo.pdi.acordar',
        'desarrollo.pdi.seguimiento',
        'desarrollo.pdi.vincular',
        'desarrollo.pdi.cerrar',
        'desarrollo.pdi.gantt',
        'desarrollo.pdi.ver',
    ]);
});

it('el colaborador recibe solo las dos claves terminadas en _propio', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/pdi/permisos')->assertOk()->assertJsonPath('data.claves', ['desarrollo.pdi.ver_propio', 'desarrollo.pdi.aceptar_propio']);
});

it('el autorizador reparte las claves por rol', function () {
    $autorizador = new AutorizadorDePdi;
    $propias = [ClaveDePermisoPdi::VerPropio, ClaveDePermisoPdi::AceptarPropio];

    foreach (ClaveDePermisoPdi::cases() as $clave) {
        $esPropia = in_array($clave, $propias, true);
        expect($autorizador->permite(true, $clave))->toBe(! $esPropia)->and($autorizador->permite(false, $clave))->toBe($esPropia);
    }
});

it('el middleware acepta varias claves separadas por | y basta una', function () {
    Route::middleware(['api', 'auth:sanctum', 'permiso:desarrollo.pdi.ver|desarrollo.pdi.ver_propio'])->get('/api/pdi-prueba/alternativas', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'auth:sanctum', 'permiso:desarrollo.pdi.gestionar|desarrollo.pdi.cerrar'])->get('/api/pdi-prueba/solo-admin', fn () => response()->json(['ok' => true]));

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/pdi-prueba/alternativas')->assertOk();
    $this->getJson('/api/pdi-prueba/solo-admin')->assertForbidden();

    actuarComoAdminDeTenant();
    $this->getJson('/api/pdi-prueba/alternativas')->assertOk();
    $this->getJson('/api/pdi-prueba/solo-admin')->assertOk();
});

it('no existe ningún endpoint DELETE en el PDI', function () {
    $metodos = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/pdi'))
        ->flatMap(fn ($r) => $r->methods())
        ->unique();

    expect($metodos->contains('DELETE'))->toBeFalse();
});

it('las claves de Rutas y Adecuación siguen resolviéndose con el middleware ampliado', function () {
    actuarComoAdminDeTenant();
    $this->getJson('/api/adecuaciones/opciones')->assertOk();
    $this->getJson('/api/rutas/opciones')->assertOk();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/adecuaciones/opciones')->assertForbidden();
});
