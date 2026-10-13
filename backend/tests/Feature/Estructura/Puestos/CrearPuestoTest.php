<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->competencia = CompetenciaModel::factory()->create(['nombre' => 'Trabajo en equipo']);
});

function enviarPuesto(mixed $prueba, array $cuerpo): TestResponse
{
    return $prueba->postJson('/api/puestos', $cuerpo);
}

it('crea el puesto Activo con código, versión 1, autor, funciones ordenadas y textos recortados', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $respuesta = enviarPuesto($this, datosPuesto($this->competencia->id, [
        'nombre' => '  Analista de datos  ',
        'funciones' => [
            ['descripcion' => '  Segunda en el cuerpo  ', 'tipo' => 'Complementaria'],
            ['descripcion' => 'Primera esencial', 'tipo' => 'Esencial'],
        ],
    ]))->assertCreated();

    $puesto = PuestoModel::query()->firstOrFail();
    expect($respuesta->json('data.codigo'))->toMatch('/^PU-\d{3}$/')
        ->and($respuesta->json('data.codigo'))->toBe(sprintf('PU-%03d', $puesto->id))
        ->and($respuesta->json('data.estado'))->toBe('Activo')
        ->and($respuesta->json('data.version'))->toBe(1)
        ->and($respuesta->json('data.nombre'))->toBe('Analista de datos')
        ->and($respuesta->json('data.funciones'))->toBe([
            ['orden' => 1, 'descripcion' => 'Segunda en el cuerpo', 'tipo' => 'Complementaria'],
            ['orden' => 2, 'descripcion' => 'Primera esencial', 'tipo' => 'Esencial'],
        ])
        ->and($respuesta->json('data.competencias.0.nombre'))->toBe('Trabajo en equipo')
        ->and($puesto->created_by)->toBe($admin->id)
        ->and($puesto->updated_by)->toBeNull();
});

it('el código sigue creciendo pasado el 999', function () {
    PuestoModel::factory()->create(['id' => 999, 'nombre' => 'Puesto 999']);
    actuarComoAdmin();

    $respuesta = enviarPuesto($this, datosPuesto($this->competencia->id))->assertCreated();

    expect($respuesta->json('data.codigo'))->toBe('PU-1000');
});

it('exige sesión y deniega al Usuario', function () {
    $cuerpo = datosPuesto($this->competencia->id);

    $this->postJson('/api/puestos', $cuerpo)->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/puestos', $cuerpo)->assertForbidden();
    expect(PuestoModel::query()->count())->toBe(0);
});

it('valida nombre, descripción y misión: obligatorios, de solo espacios y con sus límites', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['nombre' => '', 'descripcion' => '   ', 'mision' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre', 'descripcion', 'mision']);

    enviarPuesto($this, datosPuesto($this->competencia->id, [
        'nombre' => str_repeat('n', 51),
        'descripcion' => str_repeat('d', 201),
        'mision' => str_repeat('m', 201),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'descripcion', 'mision']);

    enviarPuesto($this, datosPuesto($this->competencia->id, [
        'nombre' => str_repeat('n', 50),
        'descripcion' => str_repeat('d', 200),
        'mision' => str_repeat('m', 200),
    ]))->assertCreated();
});

it('rechaza un nombre repetido sin distinguir mayúsculas ni espacios, también frente a un puesto inactivo', function () {
    PuestoModel::factory()->create(['nombre' => 'Analista de datos']);
    PuestoModel::factory()->inactivo()->create(['nombre' => 'Jefe de talento']);
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['nombre' => '  ANALISTA DE DATOS ']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.nombre.0', 'Ya existe un puesto con ese nombre.');
    enviarPuesto($this, datosPuesto($this->competencia->id, ['nombre' => 'jefe de talento']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nombre');

    expect(PuestoModel::query()->count())->toBe(2);
});

it('dos creaciones con el mismo nombre dejan un solo puesto', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['nombre' => 'Único']))->assertCreated();
    enviarPuesto($this, datosPuesto($this->competencia->id, ['nombre' => 'único']))->assertUnprocessable();

    expect(PuestoModel::query()->count())->toBe(1);
});

it('exige al menos una función y al menos una esencial', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => []]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.funciones.0', 'Agrega al menos una función.');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => [
        ['descripcion' => 'Solo complementaria', 'tipo' => 'Complementaria'],
    ]]))->assertUnprocessable()->assertJsonPath('errors.funciones.0', 'Incluye al menos una función esencial.');
});

it('rechaza funciones repetidas y descripciones de más de 500 caracteres', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => [
        ['descripcion' => 'Recopilar datos', 'tipo' => 'Esencial'],
        ['descripcion' => '  recopilar DATOS ', 'tipo' => 'Complementaria'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('funciones.1.descripcion');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => [
        ['descripcion' => str_repeat('f', 501), 'tipo' => 'Esencial'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('funciones.0.descripcion');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => [
        ['descripcion' => str_repeat('f', 500), 'tipo' => 'Esencial'],
    ]]))->assertCreated();
});

it('exige un requisito de Formación y uno de Experiencia y no pide Capacitación', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['requisitos' => [
        ['tipo' => 'Experiencia', 'descripcion' => '2 años.'],
    ]]))->assertUnprocessable()->assertJsonPath('errors.requisitos.0', 'Incluye al menos un requisito de formación.');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['requisitos' => [
        ['tipo' => 'Formacion', 'descripcion' => 'Título.'],
    ]]))->assertUnprocessable()->assertJsonPath('errors.requisitos.0', 'Incluye al menos un requisito de experiencia.');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['requisitos' => [
        ['tipo' => 'Formacion', 'descripcion' => 'Título.'],
        ['tipo' => 'Experiencia', 'descripcion' => '2 años.'],
    ]]))->assertCreated();
});

it('rechaza un requisito repetido del mismo tipo pero acepta el mismo texto en otro tipo', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['requisitos' => [
        ['tipo' => 'Formacion', 'descripcion' => 'Título afín.'],
        ['tipo' => 'Experiencia', 'descripcion' => '2 años.'],
        ['tipo' => 'Formacion', 'descripcion' => ' TÍTULO afín. '],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('requisitos.2.descripcion');

    $respuesta = enviarPuesto($this, datosPuesto($this->competencia->id, ['requisitos' => [
        ['tipo' => 'Formacion', 'descripcion' => 'Inglés intermedio.'],
        ['tipo' => 'Experiencia', 'descripcion' => '2 años.'],
        ['tipo' => 'Capacitacion', 'descripcion' => 'Inglés intermedio.'],
    ]]))->assertCreated();

    expect($respuesta->json('data.requisitos'))->toHaveCount(3);
});

it('valida las competencias: obligatorias, sin repetir, existentes, activas y con grado', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    $sinGradoD = CompetenciaModel::factory()->create();
    CompetenciaGradoModel::query()->where('competencia_id', $sinGradoD->id)->where('grado', 'D')->delete();
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => []]))
        ->assertUnprocessable()->assertJsonPath('errors.competencias.0', 'Agrega al menos una competencia.');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => [
        ['competencia_id' => $this->competencia->id, 'grado_requerido' => 'A'],
        ['competencia_id' => $this->competencia->id, 'grado_requerido' => 'B'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('competencias.1.competencia_id');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => [
        ['competencia_id' => 999999, 'grado_requerido' => 'A'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('competencias.0.competencia_id');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => [
        ['competencia_id' => $inactiva->id, 'grado_requerido' => 'A'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('competencias.0.competencia_id');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => [
        ['competencia_id' => $this->competencia->id, 'grado_requerido' => 'E'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('competencias.0.grado_requerido');

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => [
        ['competencia_id' => $sinGradoD->id, 'grado_requerido' => 'D'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors(['competencias.0.grado_requerido' => 'Ese grado no existe para la competencia.']);

    enviarPuesto($this, datosPuesto($this->competencia->id, ['competencias' => [
        ['competencia_id' => $this->competencia->id],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('competencias.0.grado_requerido');

    expect(PuestoModel::query()->count())->toBe(0);
});

it('rechaza más de 20 elementos en cada lista', function () {
    $competencias = CompetenciaModel::factory()->count(21)->create();
    actuarComoAdmin();

    $funciones = array_map(fn (int $n) => ['descripcion' => "Función $n", 'tipo' => 'Esencial'], range(1, 21));
    $requisitos = array_merge(
        [['tipo' => 'Formacion', 'descripcion' => 'Título.'], ['tipo' => 'Experiencia', 'descripcion' => '2 años.']],
        array_map(fn (int $n) => ['tipo' => 'Capacitacion', 'descripcion' => "Curso $n"], range(1, 19)),
    );

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => $funciones]))
        ->assertUnprocessable()->assertJsonValidationErrors('funciones');
    enviarPuesto($this, datosPuesto($this->competencia->id, ['requisitos' => $requisitos]))
        ->assertUnprocessable()->assertJsonValidationErrors('requisitos');
    enviarPuesto($this, datosPuesto($this->competencia->id, [
        'competencias' => $competencias->map(fn ($c) => ['competencia_id' => $c->id, 'grado_requerido' => 'A'])->all(),
    ]))->assertUnprocessable()->assertJsonValidationErrors('competencias');

    // 20 exactos sí se aceptan.
    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => array_slice($funciones, 0, 20)]))->assertCreated();
});

it('respeta el límite configurable por lista', function () {
    config(['estructura.puestos.max_elementos_por_lista' => 2]);
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['funciones' => [
        ['descripcion' => 'Uno', 'tipo' => 'Esencial'],
        ['descripcion' => 'Dos', 'tipo' => 'Esencial'],
        ['descripcion' => 'Tres', 'tipo' => 'Esencial'],
    ]]))->assertUnprocessable()->assertJsonPath('errors.funciones.0', 'Un puesto admite como máximo 2 funciones.');
});

it('no deja nada a medias cuando la validación falla', function () {
    actuarComoAdmin();

    enviarPuesto($this, datosPuesto($this->competencia->id, ['mision' => '']))->assertUnprocessable();

    expect(PuestoModel::query()->count())->toBe(0);
});
