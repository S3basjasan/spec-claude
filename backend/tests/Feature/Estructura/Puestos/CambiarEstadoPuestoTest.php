<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use Illuminate\Support\Facades\DB;

it('exige sesión y deniega al Usuario', function () {
    $puesto = puestoDe();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertForbidden();
    expect($puesto->fresh()->estado)->toBe('Activo');
});

it('desactiva un puesto sin posiciones y lo reactiva sin restricciones', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.version', 1);
    expect($puesto->fresh()->estado)->toBe('Inactivo');

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Activo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Activo');
    expect($puesto->fresh()->estado)->toBe('Activo');
});

it('rechaza desactivar un puesto con posiciones Ocupadas o Vacantes, indica cuántas y sigue Activo', function () {
    $puesto = puestoDe(['nombre' => 'Jefe'], ocupadas: 2, vacantes: 1, suprimidas: 4);
    actuarComoAdmin();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])
        ->assertUnprocessable()
        ->assertJsonPath('posiciones_en_uso', 3)
        ->assertJsonPath('message', 'No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.')
        ->assertJsonValidationErrors(['estado' => 'No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.']);

    expect($puesto->fresh()->estado)->toBe('Activo')
        ->and(AuditoriaCambioModel::query()->where('entidad_id', $puesto->id)->where('campo', 'estado')->where('valor_nuevo', 'Inactivo')->exists())->toBeFalse();
});

it('usa el singular cuando la única posición en uso es una', function () {
    $puesto = puestoDe(['nombre' => 'Jefe'], vacantes: 1);
    actuarComoAdmin();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])
        ->assertUnprocessable()
        ->assertJsonPath('posiciones_en_uso', 1)
        ->assertJsonPath('message', 'No se puede desactivar el puesto: tiene 1 posición ocupada o vacante.');
});

it('las posiciones Suprimidas no impiden la desactivación', function () {
    $puesto = puestoDe(['nombre' => 'Desarrollador'], suprimidas: 3);
    actuarComoAdmin();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo');
});

it('pedir el mismo estado responde 200 sin cambios ni historial', function () {
    $activo = puestoDe(['nombre' => 'Activo']);
    $inactivo = puestoDe(['nombre' => 'Inactivo', 'estado' => 'Inactivo']);
    actuarComoAdmin();
    $antes = AuditoriaCambioModel::query()->count();

    $this->patchJson("/api/puestos/{$activo->id}/estado", ['estado' => 'Activo'])->assertOk()->assertJsonPath('data.estado', 'Activo');
    $this->patchJson("/api/puestos/{$inactivo->id}/estado", ['estado' => 'Inactivo'])->assertOk()->assertJsonPath('data.estado', 'Inactivo');

    expect(AuditoriaCambioModel::query()->count())->toBe($antes);
});

it('valida el estado y responde 404 si el puesto no existe', function () {
    $puesto = puestoDe();
    actuarComoAdmin();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", [])->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Otro'])->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->patchJson('/api/puestos/999999/estado', ['estado' => 'Inactivo'])->assertNotFound()->assertJsonPath('message', 'El puesto no existe.');
});

it('cambiar el estado no altera la versión, el editor ni la fecha de modificación', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    DB::table('puestos')->where('id', $puesto->id)->update(['updated_at' => '2020-01-01 00:00:00']);
    actuarComoAdmin();

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Activo'])->assertOk();

    $fila = DB::table('puestos')->where('id', $puesto->id)->first();
    expect($fila->version)->toBe(1)
        ->and($fila->updated_by)->toBeNull()
        ->and($fila->updated_at)->toBe('2020-01-01 00:00:00');
});

it('un puesto inactivo sigue siendo editable y el cambio de estado no obliga a recargar el formulario', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();
    $version = $puesto->fresh()->version;

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    // Se abrió el formulario antes de desactivar: la misma versión sigue siendo válida.
    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['mision' => 'Editado tras desactivar.', 'version' => $version]))
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.mision', 'Editado tras desactivar.');
});

it('el listado y el detalle reflejan el estado: el Usuario deja de verlo en el listado pero lo consulta por id', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();
    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    $this->getJson('/api/puestos?estado=Inactivo')->assertOk()->assertJsonPath('data.0.estado', 'Inactivo');
    $this->getJson('/api/puestos')->assertOk()->assertJsonCount(0, 'data');

    actuarComoUsuario();
    $this->getJson('/api/puestos')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/puestos/{$puesto->id}")->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});
