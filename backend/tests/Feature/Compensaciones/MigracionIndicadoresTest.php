<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara una base temporal con el esquema de inc-01 (todas las migraciones menos la de 01b.5), le pasa datos de
 * partida y ejecuta después la migración nueva y su reversión. No usa RefreshDatabase: quitar o restaurar una llave
 * foránea en SQLite reconstruye la tabla y eso no se puede probar dentro de una transacción.
 */
beforeEach(function () {
    $this->directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'migracion_indicadores_'.uniqid();
    File::ensureDirectoryExists($this->directorio.DIRECTORY_SEPARATOR.'antiguas');
    touch($this->directorio.DIRECTORY_SEPARATOR.'base.sqlite');

    foreach (File::files(database_path('migrations')) as $archivo) {
        if (! str_starts_with($archivo->getFilename(), '2026_11_03_000001_')) {
            File::copy($archivo->getRealPath(), $this->directorio.DIRECTORY_SEPARATOR.'antiguas'.DIRECTORY_SEPARATOR.$archivo->getFilename());
        }
    }

    config(['database.connections.migracion' => [
        'driver' => 'sqlite',
        'database' => $this->directorio.DIRECTORY_SEPARATOR.'base.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    Artisan::call('migrate', [
        '--database' => 'migracion',
        '--path' => $this->directorio.DIRECTORY_SEPARATOR.'antiguas',
        '--realpath' => true,
        '--force' => true,
    ]);
});

afterEach(function () {
    DB::purge('migracion');
    File::deleteDirectory($this->directorio);
});

function aplicarMigracionIndicadores(): void
{
    Artisan::call('migrate', ['--database' => 'migracion', '--force' => true]);
}

function revertirMigracionIndicadores(): void
{
    Artisan::call('migrate:rollback', ['--database' => 'migracion', '--step' => 1, '--force' => true]);
}

function sembrarCompetenciaLegacy(int $id): void
{
    $fila = [
        'id' => $id, 'codigo' => sprintf('COMP-%04d', $id), 'nombre' => "Competencia $id", 'nombre_clave' => "competencia $id",
        'descripcion' => 'Descripción', 'tipo' => 'Cardinal', 'estado' => 'Activo', 'version' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ];
    $columnas = Schema::connection('migracion')->getColumnListing('competencias');

    DB::connection('migracion')->table('competencias')->insert(array_intersect_key($fila, array_flip($columnas)));
}

/**
 * Categoría con el esquema de inc-01: indicadores como filas, con el `orden` indicado por clave.
 *
 * @param  array<int, string>  $indicadores  Texto por `orden` (el orden de inserción puede ser distinto).
 */
function sembrarCategoriaLegacy(int $id, int $competenciaId, array $indicadores): void
{
    $bd = DB::connection('migracion');

    $bd->table('categorias_reconocimiento')->insert([
        'id' => $id, 'codigo' => sprintf('CAT-%03d', $id), 'nombre' => "Categoría $id", 'nombre_normalizado' => "categoria $id",
        'descripcion' => 'Descripción de la categoría', 'competencia_id' => $competenciaId, 'puntuacion_base' => 100,
        'icono' => 'equipo', 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ($indicadores as $orden => $texto) {
        $bd->table('indicadores_reconocimiento')->insert([
            'categoria_reconocimiento_id' => $id, 'orden' => $orden, 'texto' => $texto,
            'texto_normalizado' => mb_strtolower($texto), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** @return array<int, string> */
function indicadoresDe(int $categoriaId): array
{
    $crudo = DB::connection('migracion')->table('categorias_reconocimiento')->where('id', $categoriaId)->value('indicadores');

    return json_decode((string) $crudo, true);
}

/** @return array<int, string> */
function llavesHacia(string $tabla, string $destino): array
{
    return collect(Schema::connection('migracion')->getForeignKeys($tabla))
        ->where('foreign_table', $destino)
        ->pluck('foreign_table')
        ->all();
}

it('copia los indicadores de cada categoría a la lista, con los mismos textos y en el mismo orden', function () {
    sembrarCompetenciaLegacy(1);
    sembrarCategoriaLegacy(1, 1, [1 => 'Indicador único de la uno']);
    sembrarCategoriaLegacy(2, 1, [1 => 'Primero cinco', 2 => 'Segundo cinco', 3 => 'Tercero cinco', 4 => 'Cuarto cinco', 5 => 'Quinto cinco']);

    aplicarMigracionIndicadores();

    expect(indicadoresDe(1))->toBe(['Indicador único de la uno'])
        ->and(indicadoresDe(2))->toBe(['Primero cinco', 'Segundo cinco', 'Tercero cinco', 'Cuarto cinco', 'Quinto cinco']);
});

it('respeta el orden guardado aunque las filas se hayan insertado desordenadas', function () {
    sembrarCompetenciaLegacy(1);
    sembrarCategoriaLegacy(1, 1, [3 => 'Tercero indicador', 1 => 'Primero indicador', 2 => 'Segundo indicador']);

    aplicarMigracionIndicadores();

    expect(indicadoresDe(1))->toBe(['Primero indicador', 'Segundo indicador', 'Tercero indicador']);
});

it('deja una lista vacía si la categoría no tenía indicadores y no falla', function () {
    sembrarCompetenciaLegacy(1);
    sembrarCategoriaLegacy(1, 1, []);

    aplicarMigracionIndicadores();

    expect(indicadoresDe(1))->toBe([]);
});

it('elimina la tabla anterior de indicadores y deja la columna', function () {
    aplicarMigracionIndicadores();

    expect(Schema::connection('migracion')->hasTable('indicadores_reconocimiento'))->toBeFalse()
        ->and(Schema::connection('migracion')->hasColumn('categorias_reconocimiento', 'indicadores'))->toBeTrue();
});

it('revierte y vuelve a aplicar sin perder textos ni orden', function () {
    sembrarCompetenciaLegacy(1);
    sembrarCategoriaLegacy(1, 1, [1 => 'Indicador único de la uno']);
    sembrarCategoriaLegacy(2, 1, [1 => 'Primero cinco', 2 => 'Segundo cinco', 3 => 'Tercero cinco', 4 => 'Cuarto cinco', 5 => 'Quinto cinco']);
    aplicarMigracionIndicadores();

    revertirMigracionIndicadores();

    $filas = DB::connection('migracion')->table('indicadores_reconocimiento')->orderBy('categoria_reconocimiento_id')->orderBy('orden')->get();
    expect(Schema::connection('migracion')->hasColumn('categorias_reconocimiento', 'indicadores'))->toBeFalse()
        ->and($filas->where('categoria_reconocimiento_id', 1)->pluck('texto')->all())->toBe(['Indicador único de la uno'])
        ->and($filas->where('categoria_reconocimiento_id', 2)->pluck('orden')->map(fn ($o) => (int) $o)->all())->toBe([1, 2, 3, 4, 5])
        ->and($filas->where('categoria_reconocimiento_id', 2)->pluck('texto_normalizado')->first())->toBe('primero cinco');

    aplicarMigracionIndicadores();

    expect(indicadoresDe(1))->toBe(['Indicador único de la uno'])
        ->and(indicadoresDe(2))->toBe(['Primero cinco', 'Segundo cinco', 'Tercero cinco', 'Cuarto cinco', 'Quinto cinco']);
});

it('quita la llave foránea hacia competencias y la restaura al revertir', function () {
    expect(llavesHacia('categorias_reconocimiento', 'competencias'))->not->toBeEmpty();

    aplicarMigracionIndicadores();
    expect(llavesHacia('categorias_reconocimiento', 'competencias'))->toBeEmpty()
        ->and(Schema::connection('migracion')->hasColumn('categorias_reconocimiento', 'competencia_id'))->toBeTrue();

    revertirMigracionIndicadores();
    expect(llavesHacia('categorias_reconocimiento', 'competencias'))->not->toBeEmpty();
});

it('se niega a revertir si una categoría apunta a una competencia que ya no existe y no cambia nada', function () {
    sembrarCompetenciaLegacy(1);
    sembrarCategoriaLegacy(1, 1, [1 => 'Indicador de la uno']);
    aplicarMigracionIndicadores();
    DB::connection('migracion')->table('categorias_reconocimiento')->where('id', 1)->update(['competencia_id' => 999]);

    expect(fn () => revertirMigracionIndicadores())->toThrow(RuntimeException::class, 'apuntan a competencias que ya no existen');

    expect(Schema::connection('migracion')->hasTable('indicadores_reconocimiento'))->toBeFalse()
        ->and(Schema::connection('migracion')->hasColumn('categorias_reconocimiento', 'indicadores'))->toBeTrue()
        ->and(indicadoresDe(1))->toBe(['Indicador de la uno'])
        ->and(llavesHacia('categorias_reconocimiento', 'competencias'))->toBeEmpty();
});
