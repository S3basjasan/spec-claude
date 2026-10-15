<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Support\Facades\DB;

it('desactiva una unidad sin dependientes activas ni posiciones vigentes: Inactiva y fecha de supresión de hoy', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactiva')
        ->assertJsonPath('data.fecha_supresion', now()->toDateString());

    expect($a['reclutamiento']->fresh()->estado)->toBe('Inactiva');
});

it('rechaza desactivar con dependientes activas e indica cuántas', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['raiz'])->create();

    $this->patchJson("/api/unidades/{$a['raiz']->id}/estado", ['estado' => 'Inactiva'])
        ->assertUnprocessable()
        ->assertJsonPath('dependientes_activas', 3)
        ->assertJsonPath('posiciones_vigentes', 0)
        ->assertJsonPath('message', 'No se puede desactivar la unidad: tiene 3 unidades dependientes activas.');

    expect($a['raiz']->fresh()->estado)->toBe('Activa')->and($a['raiz']->fresh()->fecha_supresion)->toBeNull();
});

it('rechaza desactivar con posiciones Ocupadas o Vacantes e indica cuántas, sin contar las Suprimidas', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    posicionesDeUnidad($a['reclutamiento'], 'Ocupada', 2);
    posicionesDeUnidad($a['reclutamiento'], 'Vacante');
    posicionesDeUnidad($a['reclutamiento'], 'Suprimida', 4);

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])
        ->assertUnprocessable()
        ->assertJsonPath('posiciones_vigentes', 3)
        ->assertJsonPath('dependientes_activas', 0)
        ->assertJsonPath('message', 'No se puede desactivar la unidad: tiene 3 posiciones ocupadas o vacantes.');
    expect($a['reclutamiento']->fresh()->estado)->toBe('Activa');
});

it('con dependientes activas y posiciones vigentes indica ambas cantidades', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    posicionesDeUnidad($a['talento'], 'Vacante');

    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No se puede desactivar la unidad: tiene 1 unidad dependiente activa y 1 posición ocupada o vacante.');
});

it('desactiva si las únicas dependientes están inactivas y las únicas posiciones están Suprimidas', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['reclutamiento'])->inactiva()->create();
    posicionesDeUnidad($a['reclutamiento'], 'Suprimida', 2);

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk()->assertJsonPath('data.estado', 'Inactiva');
});

it('reactiva con el padre activo: Activa y sin fecha de supresión, sin reactivar sus dependientes', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $hija = UnidadModel::factory()->conPadre($a['reclutamiento'])->inactiva()->create();
    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Activa'])
        ->assertOk()->assertJsonPath('data.estado', 'Activa')->assertJsonPath('data.fecha_supresion', null);

    expect($hija->fresh()->estado)->toBe('Inactiva');
});

it('rechaza reactivar con el padre inactivo', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();
    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Activa'])
        ->assertUnprocessable()
        ->assertJsonPath('motivo', 'padre_inactivo')
        ->assertJsonPath('message', 'No se puede reactivar la unidad: su unidad padre está inactiva.');
    expect($a['reclutamiento']->fresh()->estado)->toBe('Inactiva');
});

it('reactivar una raíz con otra raíz activa se rechaza, y sin otra raíz activa procede', function () {
    actuarComoAdmin();
    $inactiva = UnidadModel::factory()->raiz()->inactiva()->create(['nombre' => 'Raíz vieja']);
    $activa = UnidadModel::factory()->raiz()->create(['nombre' => 'Raíz vigente']);

    $this->patchJson("/api/unidades/{$inactiva->id}/estado", ['estado' => 'Activa'])
        ->assertUnprocessable()->assertJsonPath('motivo', 'raiz_activa_existente')
        ->assertJsonPath('message', 'No se puede reactivar la unidad: ya existe una unidad raíz activa.');

    DB::table('unidades_organizacionales')->where('id', $activa->id)->update(['estado' => 'Inactiva', 'fecha_supresion' => now()->toDateString()]);
    $this->patchJson("/api/unidades/{$inactiva->id}/estado", ['estado' => 'Activa'])->assertOk()->assertJsonPath('data.estado', 'Activa');
});

it('pedir el estado que ya tiene responde 200 sin cambios ni historial', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Activa'])->assertOk()->assertJsonPath('data.estado', 'Activa');

    expect(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('cambiar el estado no modifica versión, updated_by ni updated_at', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $antes = $a['reclutamiento']->fresh();
    $this->travel(5)->minutes();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();
    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Activa'])->assertOk();

    $despues = $a['reclutamiento']->fresh();
    expect($despues->version)->toBe(1)->and($despues->updated_by)->toBeNull()->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});

it('el historial recibe el estado y la fecha de supresión con el usuario', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();

    $filas = AuditoriaCambioModel::query()->where('entidad_id', $a['reclutamiento']->id)->orderBy('id')->get();
    expect($filas->map(fn ($f) => [$f->campo, $f->valor_anterior, $f->valor_nuevo])->all())
        ->toBe([['estado', 'Activa', 'Inactiva'], ['fecha_supresion', null, now()->toDateString()]])
        ->and($filas->pluck('user_id')->unique()->count())->toBe(1);
});

it('una posición que ya existía impide desactivar y la unidad no cambia', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    posicionesDeUnidad($a['desarrollo'], 'Ocupada');

    $this->patchJson("/api/unidades/{$a['desarrollo']->id}/estado", ['estado' => 'Inactiva'])->assertUnprocessable();

    expect($a['desarrollo']->fresh()->estado)->toBe('Activa')->and(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('valida el estado y responde 404, 401 y 403', function () {
    $a = arbolDeEjemplo();
    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva'])->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva'])
        ->assertForbidden()->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);

    actuarComoAdmin();
    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Otro'])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", [])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->patchJson('/api/unidades/999/estado', ['estado' => 'Inactiva'])->assertNotFound()->assertExactJson(['message' => 'La unidad no existe.']);
});
