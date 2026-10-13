<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoRequisitoModel;
use Illuminate\Testing\TestResponse;

function nombresDelListado(TestResponse $respuesta): array
{
    return array_map(fn (array $p) => $p['nombre'], $respuesta->json('data'));
}

it('exige sesión para listar y consultar', function () {
    $puesto = puestoDe();

    $this->getJson('/api/puestos')->assertUnauthorized();
    $this->getJson("/api/puestos/{$puesto->id}")->assertUnauthorized();
});

it('al Usuario solo le entrega los puestos activos aunque pida otro estado', function () {
    puestoDe(['nombre' => 'Activo uno']);
    puestoDe(['nombre' => 'Inactivo uno', 'estado' => 'Inactivo']);
    actuarComoUsuario();

    expect(nombresDelListado($this->getJson('/api/puestos')))->toBe(['Activo uno'])
        ->and(nombresDelListado($this->getJson('/api/puestos?estado=Todos')))->toBe(['Activo uno'])
        ->and(nombresDelListado($this->getJson('/api/puestos?estado=Inactivo')))->toBe(['Activo uno']);
});

it('al Administrador le entrega por defecto los activos y puede pedir inactivos o todos', function () {
    puestoDe(['nombre' => 'Activo uno']);
    puestoDe(['nombre' => 'Inactivo uno', 'estado' => 'Inactivo']);
    actuarComoAdmin();

    expect(nombresDelListado($this->getJson('/api/puestos')))->toBe(['Activo uno'])
        ->and(nombresDelListado($this->getJson('/api/puestos?estado=Inactivo')))->toBe(['Inactivo uno'])
        ->and(nombresDelListado($this->getJson('/api/puestos?estado=Todos')))->toBe(['Activo uno', 'Inactivo uno']);
});

it('rechaza valores inválidos de estado y tamaño de página', function () {
    actuarComoAdmin();

    $this->getJson('/api/puestos?estado=Otro')->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->getJson('/api/puestos?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/puestos?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/puestos?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('busca por código y por nombre sin distinguir tildes ni mayúsculas, con % literal', function () {
    $gestion = puestoDe(['nombre' => 'Ejecutivo de gestión comercial']);
    puestoDe(['nombre' => 'Cobertura 100% regional']);
    puestoDe(['nombre' => 'Analista']);
    actuarComoUsuario();

    expect(nombresDelListado($this->getJson('/api/puestos?q=gestion')))->toBe(['Ejecutivo de gestión comercial'])
        ->and(nombresDelListado($this->getJson('/api/puestos?q=GESTIÓN')))->toBe(['Ejecutivo de gestión comercial'])
        ->and(nombresDelListado($this->getJson('/api/puestos?q='.strtolower($gestion->fresh()->codigo))))->toBe(['Ejecutivo de gestión comercial'])
        ->and(nombresDelListado($this->getJson('/api/puestos?q=100%25')))->toBe(['Cobertura 100% regional'])
        ->and(nombresDelListado($this->getJson('/api/puestos?q=inexistente')))->toBe([]);
});

it('ordena por nombre y pagina con 10 por defecto y 50 como máximo', function () {
    foreach (range(1, 12) as $n) {
        puestoDe(['nombre' => sprintf('Puesto %02d', $n)]);
    }
    actuarComoUsuario();

    $primera = $this->getJson('/api/puestos');
    $segunda = $this->getJson('/api/puestos?page=2');
    $grande = $this->getJson('/api/puestos?per_page=50');

    expect($primera->json('data'))->toHaveCount(10)
        ->and($primera->json('meta.total'))->toBe(12)
        ->and($primera->json('meta.last_page'))->toBe(2)
        ->and($primera->json('data.0.nombre'))->toBe('Puesto 01')
        ->and(nombresDelListado($segunda))->toBe(['Puesto 11', 'Puesto 12'])
        ->and($grande->json('data'))->toHaveCount(12);
});

it('el listado trae solo id, código, nombre, descripción y estado', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoUsuario();

    $this->getJson('/api/puestos')->assertOk()->assertJsonPath('data.0', [
        'id' => $puesto->id,
        'codigo' => $puesto->fresh()->codigo,
        'nombre' => 'Analista',
        'descripcion' => $puesto->descripcion,
        'estado' => 'Activo',
    ]);
});

it('el detalle trae misión, versión, funciones por orden, requisitos por tipo y competencias con datos vivos', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    PuestoRequisitoModel::query()->create(['puesto_id' => $puesto->id, 'tipo' => 'Capacitacion', 'descripcion' => 'Curso de SQL.']);
    $competencia = CompetenciaModel::query()->find($puesto->competenciasAsignadas()->first()->competencia_id);
    actuarComoUsuario();

    $respuesta = $this->getJson("/api/puestos/{$puesto->id}")->assertOk();

    expect($respuesta->json('data.mision'))->toBe($puesto->mision)
        ->and($respuesta->json('data.version'))->toBe(1)
        ->and($respuesta->json('data.estado'))->toBe('Activo')
        ->and(array_column($respuesta->json('data.funciones'), 'orden'))->toBe([1, 2])
        ->and($respuesta->json('data.funciones.0.tipo'))->toBe('Esencial')
        ->and(array_column($respuesta->json('data.requisitos'), 'tipo'))->toBe(['Formacion', 'Experiencia', 'Capacitacion'])
        ->and($respuesta->json('data.competencias.0'))->toBe([
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'tipo' => $competencia->tipo,
            'estado' => 'Activo',
            'grado_requerido' => 'B',
        ]);
});

it('el detalle de un puesto inactivo responde 200 a ambos roles y uno inexistente 404', function () {
    $puesto = puestoDe(['nombre' => 'Viejo', 'estado' => 'Inactivo']);

    actuarComoUsuario();
    $this->getJson("/api/puestos/{$puesto->id}")->assertOk()->assertJsonPath('data.estado', 'Inactivo');
    $this->getJson('/api/puestos/999999')->assertNotFound()->assertJsonPath('message', 'El puesto no existe.');

    actuarComoAdmin();
    $this->getJson("/api/puestos/{$puesto->id}")->assertOk();
});

it('refleja de inmediato los cambios de la competencia en el diccionario sin cambiar el estado del puesto', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    $competencia = CompetenciaModel::query()->find($puesto->competenciasAsignadas()->first()->competencia_id);
    actuarComoAdmin();

    $competencia->update(['nombre' => 'Nombre nuevo', 'nombre_clave' => 'nombre nuevo']);
    $this->getJson("/api/puestos/{$puesto->id}")->assertOk()->assertJsonPath('data.competencias.0.nombre', 'Nombre nuevo');

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->getJson("/api/puestos/{$puesto->id}")->assertOk()
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.competencias.0.estado', 'Inactivo')
        ->assertJsonPath('data.competencias.0.grado_requerido', 'B');
});

it('no expone ninguna ruta DELETE bajo /api/puestos', function () {
    $puesto = puestoDe();
    actuarComoAdmin();

    expect($this->deleteJson("/api/puestos/{$puesto->id}")->status())->toBeIn([404, 405]);
});
