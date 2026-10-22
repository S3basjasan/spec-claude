<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;

beforeEach(function () {
    $this->falsos = rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->crear = fn (array $cuerpo) => $this->postJson('/api/rutas', $cuerpo);
});

it('crea la ruta en Borrador con código CAR-0001, niveles ordenados y auditoría', function () {
    $r = ($this->crear)(cuerpoRuta(['nombre' => 'Ruta de datos']))
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'CAR-0001')
        ->assertJsonPath('data.estado', 'Borrador')
        ->assertJsonPath('data.tipo', 'Especialista')
        ->assertJsonPath('data.departamento', 'Tecnología')
        ->assertJsonPath('data.version', 1)
        ->assertJsonCount(3, 'data.niveles')
        ->assertJsonPath('data.niveles.0.orden', 1)
        ->assertJsonPath('data.niveles.2.orden', 3);

    $modelo = RutaModel::query()->firstOrFail();
    expect($modelo->created_by)->toBe($this->admin->id)->and($modelo->updated_by)->toBe($this->admin->id)
        ->and($modelo->tenant_id)->toBe(1)
        ->and($modelo->nombre_clave)->toBe('ruta de datos')
        ->and($r->headers->get('Location'))->toContain('/api/rutas/'.$modelo->id)
        ->and(RutaNivelModel::query()->where('ruta_id', $modelo->id)->orderBy('orden')->pluck('puesto_id')->all())->toBe([1, 2, 3])
        ->and(accionesDeHistorial($modelo->id))->toBe(['creada', 'nivel_agregado', 'nivel_agregado', 'nivel_agregado']);
});

it('asigna códigos secuenciales por tenant', function () {
    ($this->crear)(cuerpoRuta())->assertCreated()->assertJsonPath('data.codigo', 'CAR-0001');
    ($this->crear)(cuerpoRuta())->assertCreated()->assertJsonPath('data.codigo', 'CAR-0002');
});

it('el nombre del nivel es por defecto el del puesto', function () {
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 2, 'nombre' => ''], ['puesto_id' => 3]]]))
        ->assertCreated()
        ->assertJsonPath('data.niveles.0.nombre', 'Analista de datos')
        ->assertJsonPath('data.niveles.1.nombre', 'Analista senior');
});

it('permite crear la ruta sin niveles', function () {
    ($this->crear)(cuerpoRuta(['niveles' => []]))->assertCreated()->assertJsonCount(0, 'data.niveles');
});

it('rechaza el nombre vacío, demasiado largo o duplicado sin distinguir mayúsculas ni espacios extremos', function () {
    ($this->crear)(cuerpoRuta(['nombre' => 'Ruta Contable']))->assertCreated();

    ($this->crear)(cuerpoRuta(['nombre' => '  ruta   contable ']))->assertUnprocessable()->assertJsonValidationErrors('nombre');
    ($this->crear)(cuerpoRuta(['nombre' => '   ']))->assertUnprocessable()->assertJsonValidationErrors('nombre');
    ($this->crear)(cuerpoRuta(['nombre' => str_repeat('a', 81)]))->assertUnprocessable()->assertJsonValidationErrors('nombre');
    expect(RutaModel::query()->count())->toBe(1);
});

it('otro tenant puede repetir el nombre de una ruta', function () {
    rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta Contable'], tenantId: 2);

    ($this->crear)(cuerpoRuta(['nombre' => 'Ruta Contable']))->assertCreated()->assertJsonPath('data.codigo', 'CAR-0001');
});

it('rechaza un tipo inválido y una descripción demasiado larga', function () {
    ($this->crear)(cuerpoRuta(['tipo' => 'Inventado']))->assertUnprocessable()->assertJsonValidationErrors('tipo');
    ($this->crear)(cuerpoRuta(['descripcion' => str_repeat('d', 501)]))->assertUnprocessable()->assertJsonValidationErrors('descripcion');
});

it('rechaza el departamento en una ruta Interáreas y los departamentos inexistentes o inactivos', function () {
    ($this->crear)(cuerpoRuta(['tipo' => 'Interareas', 'departamento_id' => 1]))->assertUnprocessable()->assertJsonValidationErrors('departamento_id');
    ($this->crear)(cuerpoRuta(['departamento_id' => 77]))->assertUnprocessable()->assertJsonValidationErrors('departamento_id');
    ($this->crear)(cuerpoRuta(['departamento_id' => 9]))->assertUnprocessable()->assertJsonValidationErrors('departamento_id');
    ($this->crear)(cuerpoRuta(['tipo' => 'Interareas', 'departamento_id' => null]))->assertCreated()->assertJsonPath('data.departamento_id', null);
});

it('rechaza puestos repetidos, inexistentes o inactivos', function () {
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 1], ['puesto_id' => 1]]]))->assertUnprocessable()->assertJsonValidationErrors('niveles.1.puesto_id');
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 99]]]))->assertUnprocessable()->assertJsonValidationErrors('niveles.0.puesto_id');
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 6]]]))->assertUnprocessable()->assertJsonValidationErrors('niveles.0.puesto_id');
    expect(RutaModel::query()->count())->toBe(0);
});

it('valida el nombre y la experiencia mínima del nivel', function () {
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 1, 'nombre' => str_repeat('n', 81)]]]))->assertUnprocessable()->assertJsonValidationErrors('niveles.0.nombre');
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 1, 'experiencia_minima_anios' => 61]]]))->assertUnprocessable()->assertJsonValidationErrors('niveles.0.experiencia_minima_anios');
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 1, 'experiencia_minima_anios' => -1]]]))->assertUnprocessable()->assertJsonValidationErrors('niveles.0.experiencia_minima_anios');
    ($this->crear)(cuerpoRuta(['niveles' => [['puesto_id' => 1, 'experiencia_minima_anios' => 2.46]]]))->assertCreated()->assertJsonPath('data.niveles.0.experiencia_minima_anios', 2.5);
});

it('si Estructura no responde, no crea nada y avisa con 503', function () {
    rutasFalsos(['falla' => true]);

    ($this->crear)(cuerpoRuta())->assertStatus(503)->assertJsonPath('code', 'fuente_no_disponible');
    expect(RutaModel::query()->count())->toBe(0);
});

it('el colaborador no puede crear rutas', function () {
    actuarComoUsuarioDeTenant();
    ($this->crear)(cuerpoRuta())->assertForbidden();
    expect(RutaModel::query()->count())->toBe(0);
});

it('el historial no se puede editar ni borrar desde la API', function () {
    $id = ($this->crear)(cuerpoRuta())->json('data.id');

    $this->deleteJson("/api/rutas/{$id}")->assertStatus(405);
    expect(RutaHistorialModel::query()->where('ruta_id', $id)->count())->toBe(4);
});
