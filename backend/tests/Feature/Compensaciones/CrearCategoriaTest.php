<?php

use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentCategoriaRepository;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('rechaza con 401 sin sesión y con 403 a un Usuario', function () {
    $this->postJson('/api/compensaciones/categorias', datosCategoria())->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/compensaciones/categorias', datosCategoria())->assertForbidden();
    expect(CategoriaReconocimientoModel::query()->count())->toBe(0);
});

it('crea la categoría Activa con el código CAT-001 y sus indicadores en orden', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria($competencia->id))
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'CAT-001')
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.nombre', 'Trabajo en equipo')
        ->assertJsonPath('data.puntuacion_base', 100)
        ->assertJsonPath('data.icono', 'equipo')
        ->assertJsonPath('data.competencia.id', $competencia->id)
        ->assertJsonPath('data.seleccionable', true)
        ->assertJsonPath('data.indicadores.*.orden', [1, 2])
        ->assertJsonPath('data.indicadores.*.texto', ['Comparte información con su equipo', 'Ayuda a quien lo necesita']);

    $this->assertDatabaseCount('categorias_reconocimiento', 1);
    $this->assertDatabaseCount('indicadores_reconocimiento', 2);
});

it('asigna códigos consecutivos y pasa de CAT-999 a CAT-1000', function () {
    $competencia = CompetenciaModel::factory()->create();
    CategoriaReconocimientoModel::factory()->create(['codigo' => 'CAT-999']);
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria($competencia->id))
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'CAT-1000');

    $this->postJson('/api/compensaciones/categorias', datosCategoria($competencia->id, ['nombre' => 'Otra categoría']))
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'CAT-1001');
});

it('reintenta con el siguiente código si otro proceso tomó el mismo', function () {
    CategoriaReconocimientoModel::factory()->create(['codigo' => 'CAT-001']);
    $this->app->bind(CategoriaRepository::class, fn () => new class extends EloquentCategoriaRepository
    {
        private int $llamadas = 0;

        public function siguienteCodigo(): string
        {
            return ++$this->llamadas === 1 ? 'CAT-001' : parent::siguienteCodigo();
        }
    });
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria())
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'CAT-002');
});

it('responde 503 si el código sigue en uso tras los reintentos', function () {
    CategoriaReconocimientoModel::factory()->create(['codigo' => 'CAT-001']);
    $this->app->bind(CategoriaRepository::class, fn () => new class extends EloquentCategoriaRepository
    {
        public function siguienteCodigo(): string
        {
            return 'CAT-001';
        }
    });
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria())->assertStatus(503);
    expect(CategoriaReconocimientoModel::query()->count())->toBe(1);
});

it('rechaza un nombre repetido con otras mayúsculas, tildes o espacios, también contra una inactiva', function (string $repetido) {
    CategoriaReconocimientoModel::factory()->inactiva()->create(['nombre' => 'Orientación al cliente']);
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['nombre' => $repetido]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nombre');

    expect(CategoriaReconocimientoModel::query()->count())->toBe(1);
})->with(['mayúsculas' => 'ORIENTACIÓN AL CLIENTE', 'sin tilde' => 'orientacion al cliente', 'espacios' => '   Orientación   al cliente  ']);

it('rechaza datos inválidos con el error junto a cada campo', function (array $cambios, string $campo) {
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, $cambios))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);

    expect(CategoriaReconocimientoModel::query()->count())->toBe(0);
})->with([
    'nombre vacío' => [['nombre' => '   '], 'nombre'],
    'nombre corto' => [['nombre' => 'ab'], 'nombre'],
    'nombre largo' => [['nombre' => str_repeat('a', 101)], 'nombre'],
    'descripción corta' => [['descripcion' => 'corta'], 'descripcion'],
    'descripción larga' => [['descripcion' => str_repeat('a', 501)], 'descripcion'],
    'puntuación cero' => [['puntuacion_base' => 0], 'puntuacion_base'],
    'puntuación 1001' => [['puntuacion_base' => 1001], 'puntuacion_base'],
    'puntuación decimal' => [['puntuacion_base' => 10.5], 'puntuacion_base'],
    'puntuación ausente' => [['puntuacion_base' => null], 'puntuacion_base'],
    'ícono inválido' => [['icono' => 'cohete'], 'icono'],
    'ícono ausente' => [['icono' => null], 'icono'],
    'competencia ausente' => [['competencia_id' => null], 'competencia_id'],
    'sin indicadores' => [['indicadores' => []], 'indicadores'],
    'seis indicadores' => [['indicadores' => ['Indicador uno', 'Indicador dos', 'Indicador tres', 'Indicador cuatro', 'Indicador cinco', 'Indicador seis']], 'indicadores'],
    'indicador corto' => [['indicadores' => ['cort']], 'indicadores.0'],
    'indicador largo' => [['indicadores' => [str_repeat('a', 251)]], 'indicadores.0'],
    'indicadores repetidos' => [['indicadores' => ['Trabaja en equipo', ' TRABAJA en equipo ']], 'indicadores.1'],
    'HTML en el nombre' => [['nombre' => '<b>Equipo</b>'], 'nombre'],
    'HTML en la descripción' => [['descripcion' => 'Descripción <script>alert(1)</script> válida'], 'descripcion'],
    'HTML en un indicador' => [['indicadores' => ['Indicador <img src=x onerror=alert(1)>']], 'indicadores.0'],
]);

it('acepta los límites exactos', function () {
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, [
        'nombre' => str_repeat('n', 100),
        'descripcion' => str_repeat('d', 500),
        'puntuacion_base' => 1000,
        'indicadores' => [str_repeat('i', 250), 'Indicador dos', 'Indicador tres', 'Indicador cuatro', 'Indicador cinco'],
    ]))->assertCreated();
});

it('rechaza una competencia inexistente o inactiva', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria($inactiva->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('competencia_id');

    $this->postJson('/api/compensaciones/categorias', datosCategoria(9999))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('competencia_id');

    expect(CategoriaReconocimientoModel::query()->count())->toBe(0);
});

it('rechaza los campos que el cliente no puede enviar', function (string $clave, mixed $valor) {
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, [$clave => $valor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($clave);

    expect(CategoriaReconocimientoModel::query()->count())->toBe(0);
})->with([
    ['codigo', 'CAT-777'],
    ['estado', 'Inactivo'],
    ['orden', 3],
    ['created_by', 1],
    ['updated_by', 1],
    ['created_at', '2020-01-01 00:00:00'],
    ['id', 5],
    ['updated_at', '2020-01-01T00:00:00.000000Z'],
]);

it('registra la auditoría inicial y quién la creó', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $id = $this->postJson('/api/compensaciones/categorias', datosCategoria())->assertCreated()->json('data.id');

    $categoria = CategoriaReconocimientoModel::query()->findOrFail($id);
    expect($categoria->created_by)->toBe($admin->id)
        ->and($categoria->updated_by)->toBe($admin->id);

    $filas = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $id)->get();
    expect($filas->pluck('campo')->all())->toBe(['nombre', 'descripcion', 'competencia_id', 'puntuacion_base', 'icono', 'indicadores', 'estado'])
        ->and($filas->every(fn ($f) => $f->valor_anterior === null && $f->user_id === $admin->id))->toBeTrue()
        ->and($filas->firstWhere('campo', 'indicadores')->valor_nuevo)->toBe('["Comparte información con su equipo","Ayuda a quien lo necesita"]');
});

it('guarda el texto plano sin interpretarlo', function () {
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['descripcion' => 'Cumple si 3 < 5 y 7 > 2 siempre.']))
        ->assertCreated()
        ->assertJsonPath('data.descripcion', 'Cumple si 3 < 5 y 7 > 2 siempre.');
});

it('no deja nada guardado si falla una validación de negocio', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->postJson('/api/compensaciones/categorias', datosCategoria($inactiva->id))->assertUnprocessable();

    $this->assertDatabaseCount('categorias_reconocimiento', 0);
    $this->assertDatabaseCount('indicadores_reconocimiento', 0);
    $this->assertDatabaseCount('auditoria_categorias_reconocimiento', 0);
});
