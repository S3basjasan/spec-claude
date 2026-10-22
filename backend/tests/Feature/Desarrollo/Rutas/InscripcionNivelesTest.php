<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;

beforeEach(function () {
    rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->ruta = rutaEnBd([1, 2, 3], 'Vigente');
    [$this->n1, $this->n2, $this->n3] = $this->ruta['niveles'];
    $this->inscripcion = inscripcionEnBd($this->ruta['id'], 10, $this->n1, $this->n2);
    $this->url = fn (string $sufijo, ?int $id = null) => '/api/inscripciones-ruta/'.($id ?? $this->inscripcion->id).'/'.$sufijo;
});

it('cambia el nivel actual y el objetivo y lo registra con valores anterior y nuevo', function () {
    $this->patchJson(($this->url)('niveles'), ['nivel_actual_id' => $this->n2, 'nivel_objetivo_id' => $this->n3])->assertOk()
        ->assertJsonPath('data.nivel_actual.orden', 2)->assertJsonPath('data.nivel_objetivo.orden', 3);

    $evento = RutaHistorialModel::query()->where('accion', 'niveles_cambiados')->firstOrFail();
    expect($evento->valor_anterior['nivel_actual'])->toBe('1. Nivel 1')->and($evento->valor_nuevo['nivel_actual'])->toBe('2. Nivel 2')
        ->and($evento->inscripcion_id)->toBe($this->inscripcion->id)->and($evento->user_id)->toBe($this->admin->id);
});

it('admite dejar el objetivo o el actual sin definir', function () {
    $this->patchJson(($this->url)('niveles'), ['nivel_actual_id' => null, 'nivel_objetivo_id' => null])->assertOk()
        ->assertJsonPath('data.nivel_actual', null)->assertJsonPath('data.nivel_objetivo', null);
});

it('rechaza un objetivo que no es posterior y niveles de otra ruta', function () {
    $otra = rutaEnBd([1, 2], 'Vigente');

    $this->patchJson(($this->url)('niveles'), ['nivel_actual_id' => $this->n2, 'nivel_objetivo_id' => $this->n1])->assertUnprocessable()->assertJsonPath('code', 'objetivo_no_posterior');
    $this->patchJson(($this->url)('niveles'), ['nivel_actual_id' => $otra['niveles'][0], 'nivel_objetivo_id' => null])->assertUnprocessable()->assertJsonPath('code', 'nivel_ajeno');
    $this->patchJson(($this->url)('niveles'), [])->assertUnprocessable()->assertJsonValidationErrors(['nivel_actual_id', 'nivel_objetivo_id']);
});

it('una ruta Archivada conserva sus inscripciones pero no admite cambios de nivel ni nivel alcanzado', function () {
    RutaModel::query()->whereKey($this->ruta['id'])->update(['estado' => 'Archivada']);

    $this->patchJson(($this->url)('niveles'), ['nivel_actual_id' => $this->n2, 'nivel_objetivo_id' => $this->n3])->assertUnprocessable()->assertJsonPath('code', 'ruta_archivada');
    $this->patchJson(($this->url)('alcanzar'))->assertUnprocessable()->assertJsonPath('code', 'ruta_archivada');
    expect(RutaInscripcionModel::query()->find($this->inscripcion->id)->nivel_actual_id)->toBe($this->n1);
});

it('registrar el nivel alcanzado mueve el actual al objetivo y deja el objetivo sin definir', function () {
    $this->patchJson(($this->url)('alcanzar'))->assertOk()
        ->assertJsonPath('data.estado', 'Activa')
        ->assertJsonPath('data.nivel_actual.orden', 2)
        ->assertJsonPath('data.nivel_objetivo', null)
        ->assertJsonPath('nota', 'Informativo: el cambio real de puesto lo ejecuta Gestión de Personal.')
        ->assertJsonPath('data.resuelta_at', null);

    expect(accionesDeHistorial($this->ruta['id']))->toContain('nivel_alcanzado');
});

it('si el objetivo era el último nivel, la inscripción pasa a Finalizada con fecha de resolución', function () {
    $final = inscripcionEnBd($this->ruta['id'], 11, $this->n2, $this->n3);

    $this->patchJson(($this->url)('alcanzar', $final->id))->assertOk()
        ->assertJsonPath('data.estado', 'Finalizada')
        ->assertJsonPath('data.nivel_actual.orden', 3);

    $m = RutaInscripcionModel::query()->find($final->id);
    expect($m->resuelta_at)->not->toBeNull()->and($m->resuelta_by)->toBe($this->admin->id);
});

it('no se puede registrar el nivel alcanzado sin objetivo ni en una inscripción que ya no está Activa', function () {
    $sinObjetivo = inscripcionEnBd($this->ruta['id'], 11, $this->n1, null);
    $retirada = inscripcionEnBd($this->ruta['id'], 12, $this->n1, $this->n2, 'Retirada', motivo: 'Cambio');

    $this->patchJson(($this->url)('alcanzar', $sinObjetivo->id))->assertUnprocessable()->assertJsonPath('code', 'sin_objetivo');
    $this->patchJson(($this->url)('alcanzar', $retirada->id))->assertUnprocessable()->assertJsonPath('code', 'inscripcion_no_activa');
    $this->patchJson(($this->url)('niveles', $retirada->id), ['nivel_actual_id' => $this->n1, 'nivel_objetivo_id' => $this->n3])->assertUnprocessable()->assertJsonPath('code', 'inscripcion_no_activa');
});

it('retirar exige un motivo y deja la inscripción Retirada con fecha, autor y motivo', function () {
    $this->patchJson(($this->url)('retirar'), [])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    $this->patchJson(($this->url)('retirar'), ['motivo' => '   '])->assertUnprocessable();
    $this->patchJson(($this->url)('retirar'), ['motivo' => str_repeat('m', 501)])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    expect(RutaInscripcionModel::query()->find($this->inscripcion->id)->estado)->toBe('Activa');

    $this->patchJson(($this->url)('retirar'), ['motivo' => 'Cambió de área'])->assertOk()
        ->assertJsonPath('data.estado', 'Retirada')->assertJsonPath('data.motivo', 'Cambió de área');

    $m = RutaInscripcionModel::query()->find($this->inscripcion->id);
    $evento = RutaHistorialModel::query()->where('accion', 'retirada')->firstOrFail();
    expect($m->resuelta_at)->not->toBeNull()->and($m->resuelta_by)->toBe($this->admin->id)->and($m->nivel_actual_id)->toBe($this->n1)
        ->and($evento->motivo)->toBe('Cambió de área')->and($evento->valor_anterior['estado'])->toBe('Activa')->and($evento->valor_nuevo['estado'])->toBe('Retirada');
});

it('retirar está permitido aunque la ruta esté Archivada y no se puede retirar dos veces', function () {
    RutaModel::query()->whereKey($this->ruta['id'])->update(['estado' => 'Archivada']);

    $this->patchJson(($this->url)('retirar'), ['motivo' => 'Ruta en desuso'])->assertOk();
    $this->patchJson(($this->url)('retirar'), ['motivo' => 'Otra vez'])->assertUnprocessable()->assertJsonPath('code', 'inscripcion_no_activa');
});

it('responde 404 si la inscripción no existe o es de otro tenant', function () {
    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    $deOtro = inscripcionEnBd($ajena['id'], 10, tenantId: 2);

    $this->patchJson(($this->url)('alcanzar', 9999))->assertNotFound()->assertJsonPath('code', 'inscripcion_no_encontrada');
    $this->patchJson(($this->url)('retirar', $deOtro->id), ['motivo' => 'x'])->assertNotFound();
});

it('el colaborador no puede cambiar niveles, registrar alcanzado ni retirar, y no hay DELETE', function () {
    $this->deleteJson('/api/inscripciones-ruta/'.$this->inscripcion->id)->assertNotFound();
    $this->deleteJson(($this->url)('retirar'))->assertStatus(405);

    actuarComoUsuarioDeTenant();
    $this->patchJson(($this->url)('niveles'), ['nivel_actual_id' => null, 'nivel_objetivo_id' => null])->assertForbidden();
    $this->patchJson(($this->url)('alcanzar'))->assertForbidden();
    $this->patchJson(($this->url)('retirar'), ['motivo' => 'x'])->assertForbidden();
});
