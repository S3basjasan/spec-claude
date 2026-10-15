<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * Filas del historial de una unidad, en el orden en que se escribieron: [campo, anterior, nuevo, usuario].
 *
 * @return array<int, array{0: string, 1: ?string, 2: ?string, 3: ?int}>
 */
function historialDeUnidad(int $unidadId, int $desdeId = 0): array
{
    return AuditoriaCambioModel::query()
        ->where('entidad', 'unidad')
        ->where('entidad_id', $unidadId)
        ->where('id', '>', $desdeId)
        ->orderBy('id')
        ->get()
        ->map(fn (AuditoriaCambioModel $fila) => [$fila->campo, $fila->valor_anterior, $fila->valor_nuevo, $fila->user_id])
        ->all();
}

function actuarComoAdministrador(UserModel $usuario): void
{
    Sanctum::actingAs($usuario, ['admin']);
}

beforeEach(function () {
    $this->ana = UserModel::factory()->admin()->create(['name' => 'Ana Administradora']);
    $this->beto = UserModel::factory()->admin()->create(['name' => 'Beto Administrador']);
});

it('la creación registra una fila por cada dato inicial con anterior vacío, con quién la creó', function () {
    $raiz = UnidadModel::factory()->raiz()->create();
    $tipo = tipoDeUnidad('Seccion');
    actuarComoAdministrador($this->ana);

    $id = $this->postJson('/api/unidades', datosUnidad($raiz->id, ['nombre' => 'Reclutamiento', 'tipo_unidad_id' => $tipo->id]))
        ->assertCreated()->json('data.id');

    expect(historialDeUnidad($id))->toBe([
        ['nombre', null, 'Reclutamiento', $this->ana->id],
        ['tipo_unidad_id', null, (string) $tipo->id, $this->ana->id],
        ['unidad_padre_id', null, (string) $raiz->id, $this->ana->id],
        ['estado', null, 'Activa', $this->ana->id],
    ]);
    expect(AuditoriaCambioModel::query()->where('entidad', 'unidad')->whereNull('created_at')->count())->toBe(0);
});

it('crear una raíz no registra unidad_padre_id porque no tiene valor', function () {
    actuarComoAdministrador($this->ana);

    $id = $this->postJson('/api/unidades', datosUnidad(null, ['nombre' => 'Gerencia general']))->assertCreated()->json('data.id');

    expect(array_column(historialDeUnidad($id), 0))->toBe(['nombre', 'tipo_unidad_id', 'estado']);
});

it('registra quién modificó por última vez sin alterar quién la creó, con la versión', function () {
    $raiz = UnidadModel::factory()->raiz()->create();
    actuarComoAdministrador($this->ana);
    $id = $this->postJson('/api/unidades', datosUnidad($raiz->id))->assertCreated()->json('data.id');
    $unidad = UnidadModel::query()->findOrFail($id);
    expect($unidad->created_by)->toBe($this->ana->id)->and($unidad->updated_by)->toBeNull()->and($unidad->version)->toBe(1);

    actuarComoAdministrador($this->beto);
    $this->putJson("/api/unidades/{$id}", cuerpoDeUnidad($unidad, ['nombre' => 'Selección']))
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.auditoria.created_by.nombre', 'Ana Administradora')
        ->assertJsonPath('data.auditoria.updated_by.nombre', 'Beto Administrador');

    $unidad->refresh();
    expect($unidad->created_by)->toBe($this->ana->id)->and($unidad->updated_by)->toBe($this->beto->id)->and($unidad->version)->toBe(2);
});

it('al editar escribe una fila por campo cambiado con los ids anterior y nuevo', function () {
    $a = arbolDeEjemplo();
    $nuevoTipo = tipoDeUnidad('Gerencia');
    actuarComoAdministrador($this->beto);

    $this->putJson("/api/unidades/{$a['reclutamiento']->id}", cuerpoDeUnidad($a['reclutamiento'], [
        'nombre' => 'Selección',
        'tipo_unidad_id' => $nuevoTipo->id,
        'unidad_padre_id' => $a['tecnologia']->id,
    ]))->assertOk();

    expect(historialDeUnidad($a['reclutamiento']->id))->toBe([
        ['nombre', 'Reclutamiento', 'Selección', $this->beto->id],
        ['tipo_unidad_id', (string) $a['reclutamiento']->tipo_unidad_id, (string) $nuevoTipo->id, $this->beto->id],
        ['unidad_padre_id', (string) $a['talento']->id, (string) $a['tecnologia']->id, $this->beto->id],
    ]);
});

it('reubicar una unidad con rama registra solo el cambio de unidad_padre_id de la unidad movida', function () {
    $a = arbolDeEjemplo();
    actuarComoAdministrador($this->ana);

    $this->putJson("/api/unidades/{$a['tecnologia']->id}", cuerpoDeUnidad($a['tecnologia'], ['unidad_padre_id' => $a['talento']->id]))->assertOk();

    expect(AuditoriaCambioModel::query()->where('entidad', 'unidad')->count())->toBe(1)
        ->and(historialDeUnidad($a['tecnologia']->id))->toBe([
            ['unidad_padre_id', (string) $a['raiz']->id, (string) $a['talento']->id, $this->ana->id],
        ])
        ->and(historialDeUnidad($a['desarrollo']->id))->toBe([]);
});

it('desactivar escribe estado y fecha_supresion y reactivar lo inverso, con el usuario y sin tocar updated_by', function () {
    $a = arbolDeEjemplo();
    actuarComoAdministrador($this->ana);
    $hoy = now()->toDateString();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();
    actuarComoAdministrador($this->beto);
    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Activa'])->assertOk();

    expect(historialDeUnidad($a['reclutamiento']->id))->toBe([
        ['estado', 'Activa', 'Inactiva', $this->ana->id],
        ['fecha_supresion', null, $hoy, $this->ana->id],
        ['estado', 'Inactiva', 'Activa', $this->beto->id],
        ['fecha_supresion', $hoy, null, $this->beto->id],
    ]);
    expect($a['reclutamiento']->fresh()->updated_by)->toBeNull();
});

it('una edición sin cambios no añade filas ni cambia updated_by, updated_at ni versión', function () {
    $a = arbolDeEjemplo();
    actuarComoAdministrador($this->ana);
    $antes = $a['talento']->fresh();
    $this->travel(10)->minutes();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => '  Talento humano  ']))
        ->assertOk()->assertJsonPath('data.version', 1);

    $despues = $a['talento']->fresh();
    expect(AuditoriaCambioModel::query()->count())->toBe(0)
        ->and($despues->version)->toBe(1)
        ->and($despues->updated_by)->toBeNull()
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});

it('un cambio de estado que no procede no escribe nada en el historial', function () {
    $a = arbolDeEjemplo();
    actuarComoAdministrador($this->ana);

    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva'])->assertUnprocessable();

    expect(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('las filas del historial de unidades no se pueden modificar ni borrar y no se mezclan con las de puestos', function () {
    $a = arbolDeEjemplo();
    $unidad = AuditoriaCambioModel::query()->create([
        'entidad' => 'unidad', 'entidad_id' => $a['talento']->id, 'campo' => 'nombre',
        'valor_anterior' => 'A', 'valor_nuevo' => 'B', 'user_id' => $this->ana->id,
    ]);
    AuditoriaCambioModel::query()->create([
        'entidad' => 'puesto', 'entidad_id' => $a['talento']->id, 'campo' => 'nombre',
        'valor_anterior' => 'X', 'valor_nuevo' => 'Y', 'user_id' => $this->ana->id,
    ]);

    expect(fn () => $unidad->update(['valor_nuevo' => 'C']))->toThrow(LogicException::class, 'El historial de cambios no se puede modificar.');
    expect(fn () => $unidad->delete())->toThrow(LogicException::class, 'El historial de cambios no se puede borrar.');
    expect(historialDeUnidad($a['talento']->id))->toHaveCount(1)
        ->and(AuditoriaCambioModel::query()->where('entidad', 'puesto')->count())->toBe(1);
});

it('el historial sobrevive a las ediciones y cambios de estado posteriores', function () {
    $a = arbolDeEjemplo();
    actuarComoAdministrador($this->ana);
    $this->putJson("/api/unidades/{$a['desarrollo']->id}", cuerpoDeUnidad($a['desarrollo'], ['nombre' => 'Ingeniería']))->assertOk();
    $primera = historialDeUnidad($a['desarrollo']->id);

    $this->patchJson("/api/unidades/{$a['desarrollo']->id}/estado", ['estado' => 'Inactiva'])->assertOk();
    $this->putJson("/api/unidades/{$a['desarrollo']->id}", cuerpoDeUnidad($a['desarrollo'], ['nombre' => 'Ingeniería de software']))->assertOk();

    expect(array_slice(historialDeUnidad($a['desarrollo']->id), 0, 1))->toBe($primera)
        ->and(historialDeUnidad($a['desarrollo']->id))->toHaveCount(4);
});
