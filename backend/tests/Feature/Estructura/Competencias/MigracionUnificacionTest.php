<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara una base temporal con el esquema anterior a la unificación (migraciones previas a 2026_10_23),
 * le pasa los datos de partida y ejecuta después las migraciones nuevas.
 */
beforeEach(function () {
    $this->directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'migracion_unificacion_'.uniqid();
    File::ensureDirectoryExists($this->directorio.DIRECTORY_SEPARATOR.'antiguas');
    touch($this->directorio.DIRECTORY_SEPARATOR.'base.sqlite');

    foreach (File::files(database_path('migrations')) as $archivo) {
        if (! str_starts_with($archivo->getFilename(), '2026_10_23_')) {
            File::copy($archivo->getRealPath(), $this->directorio.DIRECTORY_SEPARATOR.'antiguas'.DIRECTORY_SEPARATOR.$archivo->getFilename());
        }
    }

    config(['database.connections.migracion' => [
        'driver' => 'sqlite',
        'database' => $this->directorio.DIRECTORY_SEPARATOR.'base.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
});

afterEach(function () {
    DB::purge('migracion');
    File::deleteDirectory($this->directorio);
});

function migrarAntiguas(string $directorio): void
{
    Artisan::call('migrate', [
        '--database' => 'migracion',
        '--path' => $directorio.DIRECTORY_SEPARATOR.'antiguas',
        '--realpath' => true,
        '--force' => true,
    ]);
}

function migrarNuevas(): void
{
    Artisan::call('migrate', ['--database' => 'migracion', '--force' => true]);
}

/**
 * @param  array<string, int>  $conteos  Comportamientos por grado (A–D).
 */
function sembrarCompetenciaAntigua(int $id, string $nombre, array $conteos, int $preguntas): void
{
    $bd = DB::connection('migracion');
    $ahora = now();

    $bd->table('competencias')->insert([
        'id' => $id, 'codigo' => sprintf('COMP-%04d', $id), 'nombre' => $nombre,
        'nombre_clave' => mb_strtolower($nombre), 'descripcion' => 'd', 'tipo' => 'Cardinal',
        'estado' => 'Activo', 'version' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    foreach (['A', 'B', 'C', 'D'] as $letra) {
        $gradoId = $bd->table('competencia_grados')->insertGetId(['competencia_id' => $id, 'grado' => $letra, 'descripcion' => "grado $letra"]);

        for ($orden = 1; $orden <= $conteos[$letra]; $orden++) {
            $bd->table('competencia_comportamientos')->insert([
                'competencia_grado_id' => $gradoId, 'orden' => $orden, 'descripcion' => "c$orden",
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    if ($preguntas > 0) {
        $registroId = $bd->table('competencia_pregunta_registros')->insertGetId([
            'competencia_id' => $id, 'version' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        for ($orden = 1; $orden <= $preguntas; $orden++) {
            $bd->table('competencia_preguntas')->insert([
                'registro_id' => $registroId, 'orden' => $orden, 'pregunta' => "p$orden",
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }
}

it('conserva las competencias completas, deja inactivas las incompletas y no pierde asignaciones de puestos', function () {
    migrarAntiguas($this->directorio);

    $bd = DB::connection('migracion');
    $todos = ['A' => 4, 'B' => 4, 'C' => 4, 'D' => 4];
    sembrarCompetenciaAntigua(1, 'Completa', $todos, 4);
    sembrarCompetenciaAntigua(2, 'Sin preguntas', $todos, 0);
    sembrarCompetenciaAntigua(3, 'Comportamientos a medias', ['A' => 4, 'B' => 3, 'C' => 4, 'D' => 4], 4);
    sembrarCompetenciaAntigua(4, 'Análisis', $todos, 4);

    $bd->table('puestos')->insert([
        'id' => 1, 'codigo' => 'PUE-0001', 'nombre' => 'Analista', 'nombre_clave' => 'analista', 'nombre_busqueda' => 'analista',
        'descripcion' => 'd', 'mision' => 'm', 'estado' => 'Activo', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bd->table('puesto_competencias')->insert([
        ['puesto_id' => 1, 'competencia_id' => 1, 'grado_requerido' => 'C'],
        ['puesto_id' => 1, 'competencia_id' => 2, 'grado_requerido' => 'A'],
        ['puesto_id' => 1, 'competencia_id' => 4, 'grado_requerido' => 'D'],
    ]);

    migrarNuevas();

    // Completa: sigue activa e intacta, sin historial de estado.
    $completa = $bd->table('competencias')->where('id', 1)->first();
    expect($completa->estado)->toBe('Activo')->and((int) $completa->version)->toBe(1);

    // Incompletas: pasan a Inactivo, con historial y versión + 1.
    foreach ([2, 3] as $id) {
        $competencia = $bd->table('competencias')->where('id', $id)->first();
        expect($competencia->estado)->toBe('Inactivo')->and((int) $competencia->version)->toBe(2);

        $historial = $bd->table('competencia_estado_historial')->where('competencia_id', $id)->first();
        expect([$historial->estado_anterior, $historial->estado_nuevo, $historial->user_id])->toBe(['Activo', 'Inactivo', null]);
    }
    expect($bd->table('competencia_estado_historial')->count())->toBe(2);

    // La clave del nombre ya no distingue tildes.
    expect($bd->table('competencias')->where('id', 4)->value('nombre_clave'))->toBe('analisis');

    // Los grados quedan en la escala Letras, por posición.
    $escalaLetras = $bd->table('tipo_grados')->where('nombre', 'Letras')->value('id');
    $ordenes = $bd->table('competencia_grados')
        ->join('tipo_grado_valores', 'tipo_grado_valores.id', '=', 'competencia_grados.tipo_grado_valor_id')
        ->where('competencia_grados.competencia_id', 1)->orderBy('tipo_grado_valores.orden')
        ->pluck('tipo_grado_valores.orden')->map(fn ($o) => (int) $o)->all();
    expect($ordenes)->toBe([1, 2, 3, 4])
        ->and($bd->table('tipo_grado_valores')->where('tipo_grado_id', $escalaLetras)->count())->toBe(4);

    // Las asignaciones de puestos apuntan al grado correcto por posición y ninguna se pierde.
    $asignaciones = $bd->table('puesto_competencias')
        ->join('competencia_grados', 'competencia_grados.id', '=', 'puesto_competencias.competencia_grado_id')
        ->join('tipo_grado_valores', 'tipo_grado_valores.id', '=', 'competencia_grados.tipo_grado_valor_id')
        ->orderBy('puesto_competencias.competencia_id')
        ->pluck('tipo_grado_valores.orden', 'puesto_competencias.competencia_id')
        ->map(fn ($o) => (int) $o)->all();
    expect($asignaciones)->toBe([1 => 3, 2 => 1, 4 => 4]);

    // Las preguntas cuelgan de la competencia y las tablas "registro" ya no existen.
    expect($bd->table('competencia_preguntas')->where('competencia_id', 1)->count())->toBe(4)
        ->and(Schema::connection('migracion')->hasTable('competencia_pregunta_registros'))->toBeFalse()
        ->and(Schema::connection('migracion')->hasTable('competencia_comportamiento_registros'))->toBeFalse()
        ->and(Schema::connection('migracion')->hasColumn('puesto_competencias', 'grado_requerido'))->toBeFalse()
        ->and(Schema::connection('migracion')->hasColumn('competencia_grados', 'grado'))->toBeFalse();
});

it('siembra el catálogo de escalas con Letras como única escala activa', function () {
    migrarAntiguas($this->directorio);
    migrarNuevas();

    $bd = DB::connection('migracion');
    $escalas = $bd->table('tipo_grados')->orderBy('id')->get();

    expect($escalas->pluck('nombre')->all())->toBe(['Letras', 'Números', 'Categorías'])
        ->and($escalas->pluck('activo')->map(fn ($a) => (bool) $a)->all())->toBe([true, false, false])
        ->and($bd->table('tipo_grado_valores')->count())->toBe(12);

    // El índice único parcial impide marcar dos escalas como activas.
    expect(fn () => $bd->table('tipo_grados')->where('nombre', 'Números')->update(['activo' => true]))
        ->toThrow(QueryException::class);
});
