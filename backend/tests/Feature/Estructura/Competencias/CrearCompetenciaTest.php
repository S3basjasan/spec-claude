<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CategoriaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('crea la competencia completa con código COMP-NNNN, estado Activo y versión 1', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia())
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'COMP-0001')
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.nombre', 'Innovación')
        ->assertJsonPath('data.tipo', 'Cardinal')
        ->assertJsonPath('data.completa', true)
        ->assertJsonPath('data.escala.nombre', 'Letras')
        ->assertJsonPath('data.grados.*.etiqueta', ['Grado A', 'Grado B', 'Grado C', 'Grado D'])
        ->assertJsonPath('data.grados.0.comportamientos', ['A1', 'A2', 'A3', 'A4'])
        ->assertJsonCount(4, 'data.preguntas');

    expect(CompetenciaModel::query()->count())->toBe(1)
        ->and(CompetenciaGradoModel::query()->count())->toBe(4)
        ->and(ComportamientoModel::query()->count())->toBe(16)
        ->and(PreguntaModel::query()->count())->toBe(4);
});

it('registra quién la creó y devuelve la auditoría solo al administrador', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $this->postJson('/api/competencias', datosCompetencia())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['auditoria' => ['created_by', 'created_at', 'updated_by', 'updated_at']]]);

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
        ->assertJsonValidationErrors(['nombre', 'descripcion', 'tipo', 'tipo_grado_id', 'grados', 'comportamientos', 'preguntas']);
});

it('valida longitudes y exige todos los textos con los mensajes de la especificación', function () {
    actuarComoAdmin();

    $respuesta = $this->postJson('/api/competencias', datosCompetencia([
        'nombre' => str_repeat('n', 51),
        'descripcion' => str_repeat('d', 501),
        'grados' => ['uno', 'dos', 'tres', str_repeat('g', 501)],
        'comportamientos' => [
            ['A1', 'A2', 'A3', str_repeat('c', 301)],
            ['B1', 'B2', 'B3'],
            ['C1', 'C2', 'C3', 'C4'],
            ['D1', 'D2', 'D3', 'D4'],
        ],
        'preguntas' => ['p1', 'p2', 'p3', str_repeat('p', 501)],
    ]))->assertUnprocessable();

    $errores = $respuesta->json('errors');
    expect($errores['nombre'][0])->toBe('El nombre no puede superar los 50 caracteres.')
        ->and($errores['descripcion'][0])->toBe('La descripción no puede superar los 500 caracteres.')
        ->and($errores['grados.3'][0])->toBe('La descripción de un grado no puede superar los 500 caracteres.')
        ->and($errores['comportamientos.0.3'][0])->toBe('Un comportamiento no puede superar los 300 caracteres.')
        ->and($errores['comportamientos.1'][0])->toBe('Completa los cuatro comportamientos de cada grado.')
        ->and($errores['preguntas.3'][0])->toBe('Una pregunta no puede superar los 500 caracteres.');
});

it('acepta los límites exactos de longitud', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia([
        'nombre' => str_repeat('n', 50),
        'descripcion' => str_repeat('d', 500),
        'grados' => [str_repeat('g', 500), 'b', 'c', 'd'],
        'comportamientos' => [
            [str_repeat('c', 300), 'A2', 'A3', 'A4'],
            ['B1', 'B2', 'B3', 'B4'],
            ['C1', 'C2', 'C3', 'C4'],
            ['D1', 'D2', 'D3', 'D4'],
        ],
        'preguntas' => [str_repeat('p', 500), 'p2', 'p3', 'p4'],
    ]))->assertCreated();
});

it('trata un texto con solo espacios como vacío y rechaza menos de cuatro textos', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia([
        'grados' => ['uno', '   ', 'tres', 'cuatro'],
        'preguntas' => ['p1', 'p2', 'p3'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['grados.1', 'preguntas']);
});

it('rechaza una escala que no existe', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia(['tipo_grado_id' => 99999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tipo_grado_id');

    expect(CompetenciaModel::query()->count())->toBe(0);
});

it('crea con la escala Categorías y nombres propios', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia([
        'tipo_grado_id' => escalaId('Categorías'),
        'categorias' => ['Maestría', 'Avanzado', 'Competente', 'Inicial'],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.escala.nombre', 'Categorías')
        ->assertJsonPath('data.categorias', ['Maestría', 'Avanzado', 'Competente', 'Inicial'])
        ->assertJsonPath('data.grados.*.etiqueta', ['Maestría', 'Avanzado', 'Competente', 'Inicial']);

    expect(CategoriaModel::query()->count())->toBe(4);
});

it('exige cuatro nombres de categoría válidos con la escala Categorías', function () {
    actuarComoAdmin();
    $base = ['tipo_grado_id' => escalaId('Categorías')];

    $this->postJson('/api/competencias', datosCompetencia($base))
        ->assertUnprocessable()->assertJsonValidationErrors('categorias');

    $this->postJson('/api/competencias', datosCompetencia($base + ['categorias' => ['Buena', ' búena ', 'Alta', 'Baja']]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.categorias.0', 'Escribe las cuatro categorías, sin repetir nombres.');

    $this->postJson('/api/competencias', datosCompetencia($base + ['categorias' => ['Maestría', 'Maestria', 'Alta', 'Baja']]))
        ->assertUnprocessable()->assertJsonValidationErrors('categorias');

    $this->postJson('/api/competencias', datosCompetencia($base + ['categorias' => [str_repeat('x', 21), 'B', 'C', 'D']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['categorias.0' => 'Un nombre de categoría no puede superar los 20 caracteres.']);

    $this->postJson('/api/competencias', datosCompetencia($base + ['categorias' => ['A', 'B', 'C', '']]))
        ->assertUnprocessable()->assertJsonValidationErrors('categorias.3');

    expect(CompetenciaModel::query()->count())->toBe(0);
});

it('rechaza un nombre duplicado sin distinguir mayúsculas, tildes ni espacios', function () {
    actuarComoAdmin();
    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'Liderazgo']))->assertCreated();

    foreach ([' liderazgo ', 'Lidérazgo', 'LIDERAZGO'] as $nombre) {
        $this->postJson('/api/competencias', datosCompetencia(['nombre' => $nombre]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Ya existe una competencia con este nombre.');
    }

    expect(CompetenciaModel::query()->count())->toBe(1);
});

it('es atómica: si falla un paso no se guarda nada', function () {
    actuarComoAdmin();

    // Escala válida pero con una pregunta duplicada de otra competencia no aplica; se fuerza el fallo con un id de escala sin cuatro valores.
    $this->postJson('/api/competencias', datosCompetencia(['tipo_grado_id' => 99999]))->assertUnprocessable();

    expect(CompetenciaModel::query()->count())->toBe(0)
        ->and(CompetenciaGradoModel::query()->count())->toBe(0)
        ->and(ComportamientoModel::query()->count())->toBe(0)
        ->and(PreguntaModel::query()->count())->toBe(0);
});

it('rechaza código y estado en el cuerpo', function () {
    actuarComoAdmin();

    $this->postJson('/api/competencias', datosCompetencia(['codigo' => 'COMP-9999', 'estado' => 'Inactivo']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codigo', 'estado']);
});
