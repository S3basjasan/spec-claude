<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;
use Illuminate\Support\Facades\DB;

/*
 * Cobertura parcial de FR-007 / SC-004: las tablas `reconocimientos` y `desafios` todavía no existen. Sus puntos se copian
 * al registrarse y eso lo comprueban las pruebas de las specs de inc-02 e inc-03. Aquí se demuestra que cambiar la
 * puntuación o el estado de una categoría solo escribe en `categorias_reconocimiento` y en su auditoría.
 */

/** @return array<string, int> filas por tabla de la base, salvo las del propio módulo de categorías */
function filasFueraDeCategorias(): array
{
    $propias = ['categorias_reconocimiento', 'auditoria_categorias_reconocimiento', 'migrations'];
    $conteos = [];

    foreach (DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'") as $tabla) {
        if (! in_array($tabla->name, $propias, true)) {
            $conteos[$tabla->name] = DB::table($tabla->name)->count();
        }
    }

    return $conteos;
}

it('bajar la puntuación o cambiar el estado no escribe fuera de las categorías y su auditoría', function () {
    actuarComoAdmin();
    $objetivo = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 100]);
    $otra = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 40, 'nombre' => 'Otra']);
    $otraAntes = $otra->fresh()->getRawOriginal('updated_at');
    $antes = filasFueraDeCategorias();

    $this->putJson("/api/compensaciones/categorias/{$objetivo->id}", cuerpoDeCategoria($objetivo, ['puntuacion_base' => 60]))->assertOk();
    $this->patchJson("/api/compensaciones/categorias/{$objetivo->id}/estado", ['estado' => 'Inactivo', 'updated_at' => versionDeCategoria($objetivo->id)])->assertOk();
    $this->patchJson("/api/compensaciones/categorias/{$objetivo->id}/estado", ['estado' => 'Activo', 'updated_at' => versionDeCategoria($objetivo->id)])->assertOk();

    expect(filasFueraDeCategorias())->toBe($antes)
        ->and(ParametroModuloModel::query()->count())->toBe(0)
        ->and($otra->fresh()->getRawOriginal('updated_at'))->toBe($otraAntes)
        ->and($otra->fresh()->puntuacion_base)->toBe(40)
        ->and(AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $otra->id)->count())->toBe(0);
});

it('una categoría nueva usa la puntuación nueva mientras la bajada no toca a las demás', function () {
    actuarComoAdmin();
    $objetivo = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 100]);

    $this->putJson("/api/compensaciones/categorias/{$objetivo->id}", cuerpoDeCategoria($objetivo, ['puntuacion_base' => 60]))->assertOk();
    $nueva = $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 60]))->assertCreated();

    expect($nueva->json('data.puntuacion_base'))->toBe(60)
        ->and($objetivo->fresh()->puntuacion_base)->toBe(60);
});
