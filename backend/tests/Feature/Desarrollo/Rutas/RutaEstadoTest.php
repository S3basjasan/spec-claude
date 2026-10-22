<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;

beforeEach(function () {
    $this->falsos = rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->estado = fn (int $id, string $estado, array $extra = []) => $this->patchJson("/api/rutas/{$id}/estado", ['version' => RutaModel::query()->findOrFail($id)->version, 'estado' => $estado] + $extra);
});

it('rechaza publicar con menos de 2 niveles e indica qué falta', function () {
    $uno = rutaEnBd([1], 'Borrador');
    $ninguno = rutaEnBd([], 'Borrador');

    ($this->estado)($uno['id'], 'Vigente')->assertUnprocessable()->assertJsonPath('code', 'no_publicable')->assertJsonPath('motivos.0', 'La ruta necesita al menos 2 niveles.');
    ($this->estado)($ninguno['id'], 'Vigente')->assertUnprocessable()->assertJsonPath('code', 'no_publicable');
    expect(RutaModel::query()->find($uno['id'])->estado)->toBe('Borrador');
});

it('rechaza publicar si algún nivel tiene un puesto inactivo', function () {
    $ruta = rutaEnBd([1, 6], 'Borrador');

    ($this->estado)($ruta['id'], 'Vigente')->assertUnprocessable()->assertJsonPath('code', 'no_publicable')
        ->assertJsonPath('motivos.0', 'Estos niveles tienen un puesto inactivo: 2. Nivel 2.');
});

it('publica una ruta con al menos 2 niveles y puestos activos, y lo registra en el historial', function () {
    $ruta = rutaEnBd([1, 2], 'Borrador');

    ($this->estado)($ruta['id'], 'Vigente')->assertOk()->assertJsonPath('data.estado', 'Vigente')->assertJsonPath('data.version', 2);

    $m = RutaModel::query()->findOrFail($ruta['id']);
    $evento = RutaHistorialModel::query()->where('ruta_id', $m->id)->where('accion', 'estado_cambiado')->firstOrFail();
    expect($m->updated_by)->toBe($this->admin->id)
        ->and($evento->valor_anterior)->toBe(['estado' => 'Borrador'])->and($evento->valor_nuevo)->toBe(['estado' => 'Vigente'])->and($evento->user_id)->toBe($this->admin->id);
});

it('no permite volver a Borrador ni saltar de Borrador a Archivada', function () {
    $borrador = rutaEnBd([1, 2], 'Borrador');
    $vigente = rutaEnBd([1, 2], 'Vigente');

    ($this->estado)($borrador['id'], 'Archivada')->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    ($this->estado)($vigente['id'], 'Vigente')->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    $this->patchJson("/api/rutas/{$vigente['id']}/estado", ['version' => 1, 'estado' => 'Borrador'])->assertUnprocessable()->assertJsonValidationErrors('estado');
});

it('archiva una ruta sin inscritos sin pedir confirmación', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');

    ($this->estado)($ruta['id'], 'Archivada')->assertOk()->assertJsonPath('data.estado', 'Archivada');
});

it('archivar con inscripciones Activas pide confirmación con la cantidad y, confirmada, conserva las inscripciones', function () {
    $ruta = rutaEnBd([1, 2, 3], 'Vigente');
    inscripcionEnBd($ruta['id'], 10, $ruta['niveles'][0], $ruta['niveles'][2]);
    inscripcionEnBd($ruta['id'], 11);

    ($this->estado)($ruta['id'], 'Archivada')->assertUnprocessable()->assertJsonPath('code', 'requiere_confirmacion')->assertJsonPath('inscritos', 2);
    expect(RutaModel::query()->find($ruta['id'])->estado)->toBe('Vigente');

    ($this->estado)($ruta['id'], 'Archivada', ['confirmar_inscritos' => true])->assertOk()->assertJsonPath('data.estado', 'Archivada')->assertJsonPath('data.inscritos_count', 2);
    expect(RutaInscripcionModel::query()->where('estado', 'Activa')->count())->toBe(2);
});

it('una ruta Archivada vuelve a Vigente si cumple las condiciones de publicación', function () {
    $ok = rutaEnBd([1, 2], 'Archivada');
    $incompleta = rutaEnBd([1], 'Archivada');

    ($this->estado)($ok['id'], 'Vigente')->assertOk()->assertJsonPath('data.estado', 'Vigente');
    ($this->estado)($incompleta['id'], 'Vigente')->assertUnprocessable()->assertJsonPath('code', 'no_publicable');
});

it('responde 409 con una versión desactualizada y 404 si la ruta no existe', function () {
    $ruta = rutaEnBd([1, 2], 'Borrador');

    $this->patchJson("/api/rutas/{$ruta['id']}/estado", ['version' => 7, 'estado' => 'Vigente'])->assertStatus(409)->assertJsonPath('code', 'version_desactualizada');
    $this->patchJson('/api/rutas/9999/estado', ['version' => 1, 'estado' => 'Vigente'])->assertNotFound();
});

it('si Estructura no responde, no publica y avisa con 503', function () {
    $ruta = rutaEnBd([1, 2], 'Borrador');
    rutasFalsos(['falla' => true]);

    ($this->estado)($ruta['id'], 'Vigente')->assertStatus(503);
    expect(RutaModel::query()->find($ruta['id'])->estado)->toBe('Borrador');
});

it('el colaborador no cambia estados y no existe ningún DELETE de rutas', function () {
    $ruta = rutaEnBd([1, 2], 'Borrador');

    $this->deleteJson("/api/rutas/{$ruta['id']}")->assertStatus(405);
    $this->deleteJson("/api/rutas/{$ruta['id']}/estado")->assertStatus(405);

    actuarComoUsuarioDeTenant();
    ($this->estado)($ruta['id'], 'Vigente')->assertForbidden();
});
