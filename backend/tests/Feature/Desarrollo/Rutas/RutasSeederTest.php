<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\UseCases\ConciliarBajasDeInscripciones;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\PosicionesSeeder;
use Database\Seeders\PuestosSeeder;
use Database\Seeders\RutasSeeder;
use Database\Seeders\TiposUnidadSeeder;
use Database\Seeders\UnidadesSeeder;
use Database\Seeders\UsuariosInicialesSeeder;
use Tests\Support\Desarrollo\ColaboradoresVigentesFalso;

/** Siembra lo que necesitan las rutas, en el orden de DatabaseSeeder y sin IngresosSeeder. */
function sembrarParaRutas($prueba, array $vigentes = ['Cedula:0102030405', 'Cedula:0912345678', 'Pasaporte:AB123']): void
{
    foreach ([UsuariosInicialesSeeder::class, PuestosSeeder::class, TiposUnidadSeeder::class, UnidadesSeeder::class, PosicionesSeeder::class, PersonalSeeder::class] as $seeder) {
        $prueba->seed($seeder);
    }
    $ids = [];
    foreach ($vigentes as $clave) {
        [$tipo, $numero] = explode(':', $clave);
        $ficha = PersonalFacade::buscarPorIdentificacion(1, $tipo, $numero);
        $ids[(int) $ficha['id']] = trim($ficha['nombres'].' '.$ficha['apellidos']);
    }
    app()->instance(ColaboradoresVigentesInterface::class, new ColaboradoresVigentesFalso($ids));
    $prueba->seed(RutasSeeder::class);
}

it('siembra cuatro rutas de los cuatro tipos y estados con sus niveles', function () {
    sembrarParaRutas($this);

    $rutas = RutaModel::query()->orderBy('numero')->get();

    expect($rutas->pluck('codigo')->all())->toBe(['CAR-0001', 'CAR-0002', 'CAR-0003', 'CAR-0004'])
        ->and($rutas->pluck('tipo')->all())->toBe(['Especialista', 'Gerencial', 'Interareas', 'JovenesProfesionales'])
        ->and($rutas->pluck('estado')->all())->toBe(['Vigente', 'Vigente', 'Borrador', 'Archivada'])
        ->and($rutas->map(fn ($r) => RutaNivelModel::query()->where('ruta_id', $r->id)->count())->all())->toBe([4, 4, 2, 3])
        ->and($rutas[2]->departamento_id)->toBeNull()
        ->and(RutaNivelModel::query()->whereNotNull('experiencia_minima_anios')->count())->toBeGreaterThanOrEqual(2);
});

it('siembra inscripciones Activa, Finalizada y Retirada con su historial', function () {
    sembrarParaRutas($this);

    expect(RutaInscripcionModel::query()->distinct()->pluck('estado')->sort()->values()->all())->toBe(['Activa', 'Finalizada', 'Retirada'])
        ->and(RutaInscripcionModel::query()->where('estado', 'Retirada')->whereNull('motivo')->count())->toBe(0)
        ->and(RutaHistorialModel::query()->where('accion', 'creada')->count())->toBe(4)
        ->and(RutaHistorialModel::query()->where('accion', 'inscrita')->count())->toBe(RutaInscripcionModel::query()->count());
});

it('las inscripciones Activas siguen Activas tras la conciliación de bajas', function () {
    sembrarParaRutas($this);
    $activas = RutaInscripcionModel::query()->where('estado', 'Activa')->count();

    expect($activas)->toBeGreaterThan(0)
        ->and(app(ConciliarBajasDeInscripciones::class)->ejecutar(1))->toBe(0)
        ->and(RutaInscripcionModel::query()->where('estado', 'Activa')->count())->toBe($activas);
});

it('sin colaboradores con vínculo vigente no siembra inscripciones Activas', function () {
    sembrarParaRutas($this, []);

    expect(RutaInscripcionModel::query()->where('estado', 'Activa')->count())->toBe(0)
        ->and(RutaInscripcionModel::query()->whereIn('estado', ['Finalizada', 'Retirada'])->count())->toBeGreaterThan(0);
});

it('es repetible: una segunda corrida no duplica nada', function () {
    sembrarParaRutas($this);
    $this->seed(RutasSeeder::class);

    expect(RutaModel::query()->count())->toBe(4);
});

it('las rutas sembradas se consultan por la API y las Vigentes admiten inscripciones', function () {
    sembrarParaRutas($this);
    rutasFalsos(['vigentes' => [1 => 'x']]);
    actuarComoAdminDeTenant();

    $r = $this->getJson('/api/rutas')->assertOk();

    expect($r->json('meta.total'))->toBe(4)->and(collect($r->json('data'))->firstWhere('codigo', 'CAR-0001')['niveles_count'])->toBe(4);
});
