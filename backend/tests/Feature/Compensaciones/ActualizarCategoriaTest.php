<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * Cuerpo de edición que reproduce los datos actuales de la categoría (sin cambios), con la versión leída.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoDeEdicion(CategoriaReconocimientoModel $categoria, array $cambios = []): array
{
    $categoria->load('indicadores');

    return array_replace([
        'nombre' => $categoria->nombre,
        'descripcion' => $categoria->descripcion,
        'competencia_id' => $categoria->competencia_id,
        'puntuacion_base' => $categoria->puntuacion_base,
        'icono' => $categoria->icono,
        'indicadores' => $categoria->indicadores->pluck('texto')->all(),
        'updated_at' => versionDeCategoria($categoria->id),
    ], $cambios);
}

it('rechaza con 401 sin sesión, 403 a un Usuario y 404 si no existe', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    $cuerpo = cuerpoDeEdicion($categoria);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", $cuerpo)->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", $cuerpo)->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/compensaciones/categorias/9999', $cuerpo)->assertNotFound();
});

it('actualiza los datos, reemplaza y renumera los indicadores y registra quién la modificó', function () {
    $creador = UserModel::factory()->admin()->create();
    $editor = UserModel::factory()->admin()->create();
    $categoria = CategoriaReconocimientoModel::factory()->create(['created_by' => $creador->id, 'updated_by' => $creador->id]);
    $versionAnterior = versionDeCategoria($categoria->id);
    Sanctum::actingAs($editor, ['admin']);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, [
        'puntuacion_base' => 250,
        'indicadores' => ['Nuevo indicador uno', 'Nuevo indicador dos', 'Nuevo indicador tres'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.puntuacion_base', 250)
        ->assertJsonPath('data.indicadores.*.orden', [1, 2, 3])
        ->assertJsonPath('data.indicadores.*.texto', ['Nuevo indicador uno', 'Nuevo indicador dos', 'Nuevo indicador tres'])
        ->assertJsonPath('data.codigo', $categoria->codigo)
        ->assertJsonPath('data.estado', 'Activo');

    $categoria->refresh();
    expect($categoria->created_by)->toBe($creador->id)
        ->and($categoria->updated_by)->toBe($editor->id)
        ->and(versionDeCategoria($categoria->id))->not->toBe($versionAnterior);
    $this->assertDatabaseCount('indicadores_reconocimiento', 3);
});

it('escribe una fila de auditoría por cada campo realmente modificado', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 100]);
    actuarComoAdmin();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, [
        'puntuacion_base' => 300,
        'indicadores' => ['Indicador distinto'],
    ]))->assertOk();

    $filas = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $categoria->id)->get()->keyBy('campo');
    expect($filas->keys()->sort()->values()->all())->toBe(['indicadores', 'puntuacion_base'])
        ->and($filas['puntuacion_base']->valor_anterior)->toBe('100')
        ->and($filas['puntuacion_base']->valor_nuevo)->toBe('300')
        ->and($filas['indicadores']->valor_anterior)->toContain('Indicador 1 de')
        ->and($filas['indicadores']->valor_nuevo)->toBe('["Indicador distinto"]');
});

it('rechaza con 409 si la categoría fue modificada y no aplica nada', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 100]);
    actuarComoAdmin();
    $cuerpoViejo = cuerpoDeEdicion($categoria, ['puntuacion_base' => 500]);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, ['puntuacion_base' => 200]))->assertOk();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", $cuerpoViejo)
        ->assertStatus(409)
        ->assertJsonPath('message', 'La categoría fue modificada por otro usuario. Recarga para ver los cambios.');

    expect($categoria->fresh()->puntuacion_base)->toBe(200);
});

it('acepta un guardado sin cambios sin alterar la versión, el autor ni el historial', function () {
    $autor = UserModel::factory()->admin()->create();
    $categoria = CategoriaReconocimientoModel::factory()->create(['created_by' => $autor->id, 'updated_by' => $autor->id]);
    $version = versionDeCategoria($categoria->id);
    actuarComoAdmin();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria))
        ->assertOk()
        ->assertJsonPath('data.updated_at', $version);

    expect(versionDeCategoria($categoria->id))->toBe($version)
        ->and($categoria->fresh()->updated_by)->toBe($autor->id);
    $this->assertDatabaseCount('auditoria_categorias_reconocimiento', 0);
});

it('no considera repetido el nombre de la propia categoría y rechaza el de otra', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['nombre' => 'Trabajo en equipo']);
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Innovación']);
    actuarComoAdmin();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, ['nombre' => '  TRABAJO EN EQUIPO ']))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'TRABAJO EN EQUIPO');

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria->fresh(), ['nombre' => 'innovacion']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nombre');
});

it('conserva la competencia inactiva si no cambia y exige una activa si la cambia', function () {
    $categoria = CategoriaReconocimientoModel::factory()->conCompetenciaInactiva()->create(['puntuacion_base' => 100]);
    $inactivaOtra = CompetenciaModel::factory()->inactiva()->create();
    $activa = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, ['puntuacion_base' => 150]))
        ->assertOk()
        ->assertJsonPath('data.competencia_activa', false)
        ->assertJsonPath('data.seleccionable', false);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria->fresh(), ['competencia_id' => $inactivaOtra->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('competencia_id');

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria->fresh(), ['competencia_id' => $activa->id]))
        ->assertOk()
        ->assertJsonPath('data.competencia_activa', true)
        ->assertJsonPath('data.seleccionable', true);
});

it('edita una categoría Inactiva sin cambiar su estado', function () {
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, ['nombre' => 'Nombre corregido']))
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo');
});

it('exige la versión leída y rechaza campos prohibidos', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    actuarComoAdmin();

    $sinVersion = cuerpoDeEdicion($categoria);
    unset($sinVersion['updated_at']);
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", $sinVersion)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('updated_at');

    foreach (['codigo' => 'CAT-900', 'estado' => 'Inactivo', 'created_by' => 3] as $clave => $valor) {
        $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, [$clave => $valor]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($clave);
    }
});

it('valida las mismas reglas que al crear', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, ['indicadores' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('indicadores');
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeEdicion($categoria, ['puntuacion_base' => 1001]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('puntuacion_base');
});
