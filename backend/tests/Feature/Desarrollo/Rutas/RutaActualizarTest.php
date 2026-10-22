<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;

beforeEach(function () {
    rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->ruta = rutaEnBd([1, 2, 3], 'Borrador');
    $this->actualizar = fn (array $cambios = []) => $this->putJson("/api/rutas/{$this->ruta['id']}", cuerpoActualizarRuta($this->ruta['id'], $cambios));
});

it('edita los datos generales, sube la versión y registra el último modificador y el historial', function () {
    ($this->actualizar)(['nombre' => 'Ruta renovada', 'descripcion' => 'Nueva descripción', 'departamento_id' => 2])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Ruta renovada')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.departamento', 'Talento Humano');

    $m = RutaModel::query()->findOrFail($this->ruta['id']);
    expect($m->updated_by)->toBe($this->admin->id)->and($m->nombre_clave)->toBe('ruta renovada')->and($m->codigo)->toBe($this->ruta['codigo']);

    $evento = RutaHistorialModel::query()->where('ruta_id', $m->id)->where('accion', 'editada')->firstOrFail();
    expect($evento->valor_anterior['nombre'])->toBe('Ruta 1')->and($evento->valor_nuevo['nombre'])->toBe('Ruta renovada')->and($evento->user_id)->toBe($this->admin->id);
});

it('conserva los ids de los niveles existentes y registra el reorden', function () {
    $ids = $this->ruta['niveles'];
    $niveles = [
        ['id' => $ids[2], 'orden' => 1, 'puesto_id' => 3, 'nombre' => 'Nivel 3', 'experiencia_minima_anios' => 2.0],
        ['id' => $ids[0], 'orden' => 2, 'puesto_id' => 1, 'nombre' => 'Nivel 1', 'experiencia_minima_anios' => null],
        ['id' => $ids[1], 'orden' => 3, 'puesto_id' => 2, 'nombre' => 'Nivel 2', 'experiencia_minima_anios' => 1.0],
    ];

    ($this->actualizar)(['niveles' => $niveles])->assertOk()
        ->assertJsonPath('data.niveles.0.id', $ids[2])
        ->assertJsonPath('data.niveles.1.id', $ids[0])
        ->assertJsonPath('data.niveles.2.id', $ids[1]);

    expect(RutaNivelModel::query()->where('ruta_id', $this->ruta['id'])->orderBy('orden')->pluck('id')->map(fn ($i) => (int) $i)->all())->toBe([$ids[2], $ids[0], $ids[1]])
        ->and(accionesDeHistorial($this->ruta['id']))->toContain('nivel_reordenado');
});

it('agrega y quita niveles y registra cada cambio en el historial', function () {
    $ids = $this->ruta['niveles'];
    $niveles = [
        ['id' => $ids[0], 'orden' => 1, 'puesto_id' => 1, 'nombre' => 'Nivel 1', 'experiencia_minima_anios' => null],
        ['orden' => 2, 'puesto_id' => 4, 'nombre' => 'Jefe', 'experiencia_minima_anios' => 5],
    ];

    ($this->actualizar)(['niveles' => $niveles])->assertOk()->assertJsonCount(2, 'data.niveles');

    expect(RutaNivelModel::query()->where('ruta_id', $this->ruta['id'])->count())->toBe(2)
        ->and(RutaNivelModel::query()->whereIn('id', [$ids[1], $ids[2]])->count())->toBe(0)
        ->and(accionesDeHistorial($this->ruta['id']))->toContain('nivel_agregado', 'nivel_quitado');
});

it('permite intercambiar los puestos de dos niveles sin chocar con los únicos', function () {
    $ids = $this->ruta['niveles'];
    $niveles = [
        ['id' => $ids[0], 'orden' => 1, 'puesto_id' => 2, 'nombre' => 'Nivel 1', 'experiencia_minima_anios' => null],
        ['id' => $ids[1], 'orden' => 2, 'puesto_id' => 1, 'nombre' => 'Nivel 2', 'experiencia_minima_anios' => 1],
        ['id' => $ids[2], 'orden' => 3, 'puesto_id' => 3, 'nombre' => 'Nivel 3', 'experiencia_minima_anios' => 2],
    ];

    ($this->actualizar)(['niveles' => $niveles])->assertOk();

    expect(RutaNivelModel::query()->where('ruta_id', $this->ruta['id'])->orderBy('orden')->pluck('puesto_id')->map(fn ($i) => (int) $i)->all())->toBe([2, 1, 3]);
});

it('responde 409 si la versión no coincide', function () {
    ($this->actualizar)(['version' => 5])->assertStatus(409)->assertJsonPath('code', 'version_desactualizada');
    expect(RutaModel::query()->find($this->ruta['id'])->version)->toBe(1);
});

it('dos guardados con la misma versión: el segundo recibe el conflicto', function () {
    $cuerpo = cuerpoActualizarRuta($this->ruta['id'], ['nombre' => 'Primera']);
    $this->putJson("/api/rutas/{$this->ruta['id']}", $cuerpo)->assertOk();
    $this->putJson("/api/rutas/{$this->ruta['id']}", ['nombre' => 'Segunda'] + $cuerpo)->assertStatus(409);

    expect(RutaModel::query()->find($this->ruta['id'])->nombre)->toBe('Primera');
});

it('rechaza un orden que no es consecutivo desde 1 y los ids ajenos', function () {
    $ids = $this->ruta['niveles'];
    $base = fn (int $orden2) => [
        ['id' => $ids[0], 'orden' => 1, 'puesto_id' => 1, 'nombre' => 'a'],
        ['id' => $ids[1], 'orden' => $orden2, 'puesto_id' => 2, 'nombre' => 'b'],
    ];

    ($this->actualizar)(['niveles' => $base(3)])->assertUnprocessable()->assertJsonValidationErrors('niveles');
    ($this->actualizar)(['niveles' => [['id' => 9999, 'orden' => 1, 'puesto_id' => 1, 'nombre' => 'a']]])->assertUnprocessable()->assertJsonValidationErrors('niveles');
});

it('el nombre de otra ruta no se puede tomar, pero el propio sí se conserva', function () {
    rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta ajena']);

    ($this->actualizar)(['nombre' => 'ruta ajena'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
    ($this->actualizar)()->assertOk();
});

it('no se puede quitar un nivel que es nivel actual u objetivo de una inscripción Activa', function () {
    $ids = $this->ruta['niveles'];
    inscripcionEnBd($this->ruta['id'], 10, $ids[0], $ids[2]);
    $sinTercero = array_slice(cuerpoActualizarRuta($this->ruta['id'])['niveles'], 0, 2);

    ($this->actualizar)(['niveles' => $sinTercero])->assertUnprocessable()->assertJsonPath('code', 'nivel_en_uso');

    expect(RutaNivelModel::query()->where('ruta_id', $this->ruta['id'])->count())->toBe(3);
});

it('no se puede quitar un nivel que conserva historial de inscripciones no activas', function () {
    $ids = $this->ruta['niveles'];
    inscripcionEnBd($this->ruta['id'], 10, $ids[1], $ids[2], 'Retirada', motivo: 'Cambio');
    $sinSegundo = array_values(array_filter(cuerpoActualizarRuta($this->ruta['id'])['niveles'], fn ($n) => $n['id'] !== $ids[1]));
    foreach ($sinSegundo as $i => &$n) {
        $n['orden'] = $i + 1;
    }
    unset($n);

    ($this->actualizar)(['niveles' => $sinSegundo])->assertUnprocessable()->assertJsonPath('code', 'nivel_con_historial');
});

it('no se puede cambiar el puesto de un nivel en uso, pero sí su nombre y su experiencia mínima', function () {
    $ids = $this->ruta['niveles'];
    inscripcionEnBd($this->ruta['id'], 10, $ids[0], $ids[1]);

    $conOtroPuesto = cuerpoActualizarRuta($this->ruta['id'])['niveles'];
    $conOtroPuesto[1]['puesto_id'] = 4;
    ($this->actualizar)(['niveles' => $conOtroPuesto])->assertUnprocessable()->assertJsonPath('code', 'nivel_en_uso');

    $soloNombre = cuerpoActualizarRuta($this->ruta['id'])['niveles'];
    $soloNombre[1]['nombre'] = 'Analista renombrado';
    $soloNombre[1]['experiencia_minima_anios'] = 2.5;
    ($this->actualizar)(['niveles' => $soloNombre])->assertOk()->assertJsonPath('data.niveles.1.nombre', 'Analista renombrado')->assertJsonPath('data.niveles.1.experiencia_minima_anios', 2.5);
});

it('rechaza un reorden que deja el objetivo igual o anterior al actual', function () {
    $ids = $this->ruta['niveles'];
    inscripcionEnBd($this->ruta['id'], 10, $ids[0], $ids[1]);
    $invertidos = [
        ['id' => $ids[1], 'orden' => 1, 'puesto_id' => 2, 'nombre' => 'b'],
        ['id' => $ids[0], 'orden' => 2, 'puesto_id' => 1, 'nombre' => 'a'],
        ['id' => $ids[2], 'orden' => 3, 'puesto_id' => 3, 'nombre' => 'c'],
    ];

    ($this->actualizar)(['niveles' => $invertidos])->assertUnprocessable()->assertJsonPath('code', 'reorden_invalido');
});

it('un puesto que ya estaba y se desactivó no impide editar otros datos de la ruta', function () {
    rutasFalsos(['puestos' => [1 => ['activo' => false] + puestosDeRutas()[1]] + puestosDeRutas()]);

    ($this->actualizar)(['descripcion' => 'Ajuste menor'])->assertOk();
});

it('un puesto inactivo no se puede agregar a un nivel nuevo', function () {
    $niveles = cuerpoActualizarRuta($this->ruta['id'])['niveles'];
    $niveles[] = ['orden' => 4, 'puesto_id' => 6, 'nombre' => 'Antiguo'];

    ($this->actualizar)(['niveles' => $niveles])->assertUnprocessable()->assertJsonValidationErrors('niveles.3.puesto_id');
});

it('el colaborador no puede actualizar rutas', function () {
    actuarComoUsuarioDeTenant();

    ($this->actualizar)()->assertForbidden();
});

it('responde 404 si la ruta no existe o es de otro tenant', function () {
    $this->putJson('/api/rutas/9999', cuerpoActualizarRuta($this->ruta['id']))->assertNotFound();

    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    $this->putJson("/api/rutas/{$ajena['id']}", cuerpoActualizarRuta($ajena['id']))->assertNotFound();
});
