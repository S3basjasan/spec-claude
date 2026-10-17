<?php

use Database\Seeders\CatalogoFormativoSeeder;
use Database\Seeders\CompetenciasSeeder;
use Database\Seeders\MicroLearningSeeder;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\UsuariosInicialesSeeder;
use Illuminate\Support\Facades\DB;

it('siembra 10 micro-contenidos de los cuatro tipos y los tres estados con un quiz de 5 preguntas', function () {
    $this->seed(MicroLearningSeeder::class);

    $filas = DB::table('microcontenidos')->get();
    expect($filas)->toHaveCount(10)
        ->and($filas->pluck('tipo')->unique()->sort()->values()->all())->toBe(['Articulo', 'PDF', 'Quiz', 'Video'])
        ->and($filas->pluck('estado')->unique()->sort()->values()->all())->toBe(['Archivado', 'Borrador', 'Publicado'])
        ->and($filas->where('estado', 'Publicado')->count())->toBeGreaterThanOrEqual(5)
        ->and($filas->pluck('codigo')->all())->toBe(array_map(fn ($i) => 'MICRO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 10)));

    $quiz = $filas->firstWhere('codigo', 'MICRO-0005');
    expect($quiz->estado)->toBe('Publicado')
        ->and(DB::table('microcontenido_preguntas')->where('microcontenido_id', $quiz->id)->count())->toBe(5)
        ->and(DB::table('microcontenido_opciones')->where('es_correcta', true)->count())->toBe(7);
});

it('es idempotente', function () {
    $this->seed(MicroLearningSeeder::class);
    $this->seed(MicroLearningSeeder::class);

    expect(DB::table('microcontenidos')->count())->toBe(10);
});

it('el catálogo vincula los micro-contenidos sembrados por código sin crear otros', function () {
    $this->seed(UsuariosInicialesSeeder::class);
    $this->seed(CompetenciasSeeder::class);
    $this->seed(CatalogoFormativoSeeder::class);

    expect(DB::table('microcontenidos')->count())->toBe(10)
        ->and(DB::table('competencia_microcontenido')->count())->toBe(5);
});

it('siembra progresos e intentos de ejemplo cuando existen las fichas de Personal', function () {
    $this->seed(UsuariosInicialesSeeder::class);
    $this->seed(CompetenciasSeeder::class);
    $this->seed(MicroLearningSeeder::class);
    expect(DB::table('microcontenido_progresos')->count())->toBe(0);

    $this->seed(PersonalSeeder::class);
    $this->seed(MicroLearningSeeder::class);
    $this->seed(MicroLearningSeeder::class);

    expect(DB::table('microcontenido_progresos')->count())->toBe(6)
        ->and(DB::table('microcontenido_progresos')->where('estado', 'EnProgreso')->whereNotNull('posicion_segundos')->count())->toBe(2)
        ->and(DB::table('microcontenido_progresos')->where('estado', 'Completado')->count())->toBe(4)
        ->and(DB::table('microcontenido_intentos')->where('estado', 'Finalizado')->count())->toBe(2);
});
