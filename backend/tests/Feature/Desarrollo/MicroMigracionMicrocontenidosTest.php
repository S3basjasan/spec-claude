<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('convierte una tabla con la forma de la feature 005 y no reutiliza ids eliminados', function () {
    $migracion = require database_path('migrations/2026_10_20_000001_extend_microcontenidos_table.php');

    // Vuelve a la forma mínima de la feature 005 y siembra filas antiguas.
    $migracion->down();
    expect(Schema::hasColumn('microcontenidos', 'nombre'))->toBeTrue()->and(Schema::hasColumn('microcontenidos', 'titulo'))->toBeFalse();

    $antiguas = [
        ['nombre' => 'Guía', 'tipo' => 'Lectura', 'estado' => 'Disponible', 'formador' => 'Ana', 'url_destino' => '/a'],
        ['nombre' => 'Infografía', 'tipo' => 'Infografia', 'estado' => 'Disponible', 'formador' => null, 'url_destino' => null],
        ['nombre' => 'Audio', 'tipo' => 'Audio', 'estado' => 'Archivado', 'formador' => null, 'url_destino' => null],
    ];
    foreach ($antiguas as $fila) {
        DB::table('microcontenidos')->insert($fila + ['duracion_minutos' => 5, 'created_at' => now(), 'updated_at' => now()]);
    }
    DB::table('competencia_microcontenido')->insert(['competencia_id' => 9, 'microcontenido_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $migracion->up();

    $filas = DB::table('microcontenidos')->orderBy('id')->get();
    expect($filas->pluck('titulo')->all())->toBe(['Guía', 'Infografía', 'Audio'])
        ->and($filas->pluck('tipo')->all())->toBe(['Articulo', 'PDF', 'Video'])
        ->and($filas->pluck('estado')->all())->toBe(['Publicado', 'Publicado', 'Archivado'])
        ->and($filas->pluck('codigo')->all())->toBe(['MICRO-0001', 'MICRO-0002', 'MICRO-0003'])
        ->and($filas->first()->url)->toBe('/a')
        ->and(Schema::hasColumn('microcontenidos', 'formador'))->toBeFalse()
        ->and(DB::table('competencia_microcontenido')->where('microcontenido_id', 1)->count())->toBe(1);

    // Un id eliminado no se reutiliza (AUTOINCREMENT conservado).
    DB::table('microcontenidos')->where('id', 3)->delete();
    $nuevo = MicrocontenidoModel::query()->create(['titulo' => 'Nuevo', 'tipo' => 'Video', 'duracion_minutos' => 3]);

    expect($nuevo->id)->toBe(4)->and($nuevo->estado)->toBe('Borrador');
});
