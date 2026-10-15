<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('crea la unidad con código UO-###, estado Activa, versión 1 y fecha de hoy', function () {
    $admin = UserModel::factory()->admin()->create(['name' => 'Ana Administradora']);
    Sanctum::actingAs($admin, ['admin']);
    $raiz = UnidadModel::factory()->raiz()->create();
    UnidadModel::factory()->count(8)->conPadre($raiz)->create();

    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => '  Reclutamiento  ']))
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'UO-010')
        ->assertJsonPath('data.nombre', 'Reclutamiento')
        ->assertJsonPath('data.estado', 'Activa')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.fecha_creacion', now()->toDateString())
        ->assertJsonPath('data.fecha_supresion', null)
        ->assertJsonPath('data.nivel', 1)
        ->assertJsonPath('data.auditoria.created_by.nombre', 'Ana Administradora');

    $unidad = UnidadModel::query()->latest('id')->first();
    expect($unidad->created_by)->toBe($admin->id)->and($unidad->updated_by)->toBeNull();
});

it('la primera unidad puede ser la raíz', function () {
    actuarComoAdmin();

    $this->postJson('/api/unidades', datosUnidad(null, ['nombre' => 'Gerencia general', 'tipo_unidad_id' => tipoDeUnidad('Gerencia')->id]))
        ->assertCreated()
        ->assertJsonPath('data.unidad_padre', null)
        ->assertJsonPath('data.nivel', 0);
});

it('rechaza con 401 sin sesión y con 403 al Usuario', function () {
    $raiz = UnidadModel::factory()->raiz()->create();
    $this->postJson('/api/unidades', datosUnidad($raiz->id))->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/unidades', datosUnidad($raiz->id))
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);
    expect(UnidadModel::query()->count())->toBe(1);
});

it('exige nombre, de hasta 150 caracteres, y tipo', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();

    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors(['nombre'])->assertJsonPath('errors.nombre.0', 'El nombre es obligatorio.');
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => '   ']))->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => str_repeat('x', 151)]))->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => str_repeat('x', 150)]))->assertCreated();
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['tipo_unidad_id' => null]))->assertUnprocessable()->assertJsonValidationErrors(['tipo_unidad_id']);
    expect(UnidadModel::query()->count())->toBe(2);
});

it('responde 422 y no un error interno con un tipo o una unidad padre inexistentes', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();

    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['tipo_unidad_id' => 999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['tipo_unidad_id']);
    $this->postJson('/api/unidades', datosUnidad(999))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    expect(UnidadModel::query()->count())->toBe(1);
});

it('rechaza una unidad padre inactiva y no deja nada a medias', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();
    $inactiva = UnidadModel::factory()->conPadre($raiz)->inactiva()->create();

    $this->postJson('/api/unidades', datosUnidad($inactiva->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    expect(UnidadModel::query()->count())->toBe(2)->and(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('rechaza una segunda raíz activa y acepta una nueva si la raíz está inactiva', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();

    $this->postJson('/api/unidades', datosUnidad(null, ['nombre' => 'Otra raíz']))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    expect(UnidadModel::query()->count())->toBe(1);

    DB::table('unidades_organizacionales')->where('id', $raiz->id)->update(['estado' => 'Inactiva', 'fecha_supresion' => now()->toDateString()]);
    $this->postJson('/api/unidades', datosUnidad(null, ['nombre' => 'Nueva raíz']))->assertCreated();
});

it('acepta un nombre repetido, incluso con el mismo tipo y bajo el mismo padre', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();

    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => 'Operaciones']))->assertCreated();
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => 'Operaciones']))->assertCreated();

    expect(UnidadModel::query()->where('nombre', 'Operaciones')->count())->toBe(2);
});

it('no acepta estado ni fechas en el cuerpo', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();

    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['estado' => 'Inactiva']))->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['fecha_creacion' => '2020-01-01']))->assertUnprocessable()->assertJsonValidationErrors(['fecha_creacion']);
    $this->postJson('/api/unidades', datosUnidad($raiz->id, ['fecha_supresion' => '2020-01-01']))->assertUnprocessable()->assertJsonValidationErrors(['fecha_supresion']);
    expect(UnidadModel::query()->count())->toBe(1);
});

it('el historial recibe un registro por cada dato inicial con valor y valor anterior nulo', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);
    $raiz = UnidadModel::factory()->raiz()->create();

    $id = $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => 'Reclutamiento']))->assertCreated()->json('data.id');

    $filas = AuditoriaCambioModel::query()->where('entidad', 'unidad')->where('entidad_id', $id)->orderBy('id')->get();
    expect($filas->map(fn ($f) => [$f->campo, $f->valor_anterior, $f->valor_nuevo])->all())->toBe([
        ['nombre', null, 'Reclutamiento'],
        ['tipo_unidad_id', null, (string) tipoDeUnidad('Seccion')->id],
        ['unidad_padre_id', null, (string) $raiz->id],
        ['estado', null, 'Activa'],
    ])->and($filas->pluck('user_id')->unique()->all())->toBe([$admin->id]);
});

it('una raíz registra solo nombre, tipo y estado en el historial', function () {
    actuarComoAdmin();

    $id = $this->postJson('/api/unidades', datosUnidad(null, ['nombre' => 'Gerencia general']))->assertCreated()->json('data.id');

    expect(AuditoriaCambioModel::query()->where('entidad_id', $id)->pluck('campo')->all())->toBe(['nombre', 'tipo_unidad_id', 'estado']);
});

it('dos creaciones reciben códigos distintos y el código sigue creciendo pasando el 999', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();
    DB::statement("update sqlite_sequence set seq = 999 where name = 'unidades_organizacionales'");

    $a = $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => 'A']))->assertCreated()->json('data.codigo');
    $b = $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => 'B']))->assertCreated()->json('data.codigo');

    expect($a)->toBe('UO-1000')->and($b)->toBe('UO-1001');
});
