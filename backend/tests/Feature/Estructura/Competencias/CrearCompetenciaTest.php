<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('crea una competencia como Administrador con código COMP-NNNN, estado Activo y versión 1', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia())
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'COMP-0001')
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.nombre', 'Innovación')
        ->assertJsonPath('data.tipo', 'Cardinal')
        ->assertJsonPath('data.grados.*.grado', ['A', 'B', 'C', 'D']);

    expect(CompetenciaModel::query()->count())->toBe(1);
});

it('registra quién la creó', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $this->postJson('/api/competencias', datosCompetencia())->assertCreated();

    $competencia = CompetenciaModel::query()->first();
    expect($competencia->created_by)->toBe($admin->id)
        ->and($competencia->updated_by)->toBeNull()
        ->and($competencia->created_at)->not->toBeNull();
});

it('rechaza con 401 sin sesión y con 403 al Usuario', function () {
    $this->postJson('/api/competencias', datosCompetencia())->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/competencias', datosCompetencia())
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);
    expect(CompetenciaModel::query()->count())->toBe(0);
});

it('devuelve 422 con un error por cada campo faltante', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre', 'descripcion', 'tipo', 'grados']);
});

it('exige la descripción de cada uno de los cuatro grados', function () {
    actuarComoAdmin();
    $datos = datosCompetencia();
    unset($datos['grados']['B']);
    $datos['grados']['D'] = '   ';

    $respuesta = $this->postJson('/api/competencias', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['grados.B', 'grados.D'])
        ->assertJsonMissingValidationErrors(['grados.A', 'grados.C']);

    expect($respuesta->json('errors')['grados.B'][0])->toBe('Ingresa la descripción del grado B.');
});

it('rechaza grados con una letra extra o distinta de A, B, C y D', function () {
    actuarComoAdmin();
    $conExtra = datosCompetencia();
    $conExtra['grados']['E'] = 'Grado extra';
    $conOtraLetra = datosCompetencia(['grados' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'E' => 'e']]);

    $this->postJson('/api/competencias', $conExtra)->assertUnprocessable()->assertJsonValidationErrors('grados');
    $this->postJson('/api/competencias', $conOtraLetra)->assertUnprocessable()->assertJsonValidationErrors(['grados', 'grados.D']);
    expect(CompetenciaModel::query()->count())->toBe(0);
});

it('respeta los máximos de 50 y 500 caracteres', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia(['nombre' => str_repeat('a', 51)]))
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $this->postJson('/api/competencias', datosCompetencia(['descripcion' => str_repeat('a', 501)]))
        ->assertUnprocessable()->assertJsonValidationErrors('descripcion');
    $this->postJson('/api/competencias', datosCompetencia(['grados' => ['A' => str_repeat('a', 501), 'B' => 'b', 'C' => 'c', 'D' => 'd']]))
        ->assertUnprocessable()->assertJsonValidationErrors('grados.A');

    $this->postJson('/api/competencias', datosCompetencia([
        'nombre' => str_repeat('a', 50),
        'descripcion' => str_repeat('b', 500),
        'grados' => ['A' => str_repeat('c', 500), 'B' => 'b', 'C' => 'c', 'D' => 'd'],
    ]))->assertCreated();
});

it('acepta solo los tipos Cardinal y Especifica', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia(['tipo' => 'otra']))
        ->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->postJson('/api/competencias', datosCompetencia(['tipo' => 'cardinal']))
        ->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->postJson('/api/competencias', datosCompetencia(['tipo' => 'Especifica']))
        ->assertCreated()->assertJsonPath('data.tipo', 'Especifica');
});

it('rechaza un nombre duplicado sin distinguir mayúsculas ni espacios alrededor', function () {
    actuarComoAdmin();
    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'Liderazgo']))->assertCreated();

    $this->postJson('/api/competencias', datosCompetencia(['nombre' => '  LIDERAZGO ']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nombre')
        ->assertJsonPath('errors.nombre.0', 'Ya existe una competencia con ese nombre.');
    expect(CompetenciaModel::query()->count())->toBe(1);
});

it('trata como duplicado un nombre que solo cambia en mayúsculas con acentos', function () {
    actuarComoAdmin();
    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'Innovación']))->assertCreated();

    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'INNOVACIÓN']))
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    expect(CompetenciaModel::query()->count())->toBe(1);
});

it('rechaza un nombre igual al de una competencia inactiva', function () {
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'retirada']))
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');
});

it('guarda el nombre recortado e ignora el código y el estado enviados', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia(['nombre' => '  Innovación  ', 'codigo' => 'XXX-999', 'estado' => 'Inactivo']))
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Innovación')
        ->assertJsonPath('data.codigo', 'COMP-0001')
        ->assertJsonPath('data.estado', 'Activo');
});
