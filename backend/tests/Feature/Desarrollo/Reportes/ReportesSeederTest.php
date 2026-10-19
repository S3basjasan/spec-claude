<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use Database\Seeders\ActividadesSeeder;
use Database\Seeders\CatalogoFormativoSeeder;
use Database\Seeders\CompetenciasSeeder;
use Database\Seeders\DnfSeeder;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\PlanFormacionSeeder;
use Database\Seeders\PosicionesSeeder;
use Database\Seeders\PuestosSeeder;
use Database\Seeders\ReportesSeeder;
use Database\Seeders\TiposUnidadSeeder;
use Database\Seeders\UnidadesSeeder;
use Database\Seeders\UsuariosInicialesSeeder;
use Illuminate\Support\Facades\DB;

/** Siembra en el orden de DatabaseSeeder, sin IngresosSeeder (que no interviene en estas actividades). */
function sembrarReportes($prueba): void
{
    foreach ([UsuariosInicialesSeeder::class, CompetenciasSeeder::class, PuestosSeeder::class, CatalogoFormativoSeeder::class, TiposUnidadSeeder::class,
        UnidadesSeeder::class, PosicionesSeeder::class, PersonalSeeder::class, DnfSeeder::class, PlanFormacionSeeder::class, ActividadesSeeder::class, ReportesSeeder::class] as $seeder) {
        $prueba->seed($seeder);
    }
    actividadFalsos(['hoy' => now()->toDateString()]);
    departamentosActuales([]);
    actuarComoAdminDeTenant();
}

it('siembra encuestas, seguimientos de los tres hitos y evaluaciones de impacto', function () {
    sembrarReportes($this);

    expect(EncuestaSatisfaccionModel::query()->count())->toBeGreaterThan(0)
        ->and(SeguimientoImpactoModel::query()->pluck('hito')->unique()->sort()->values()->all())->toBe([30, 60, 90])
        ->and(ImpactoAccionModel::query()->count())->toBe(3)
        ->and(EncuestaSatisfaccionModel::query()->pluck('calificacion')->every(fn ($n) => $n >= 1 && $n <= 5))->toBeTrue()
        ->and(SeguimientoImpactoModel::query()->get()->every(fn ($s) => $s->aplicacion >= 1 && $s->aplicacion <= 5 && $s->apoyo >= 1 && $s->apoyo <= 5))->toBeTrue();
});

it('incluye un caso de alerta de reingreso', function () {
    sembrarReportes($this);

    $filas = $this->getJson('/api/reportes/seguimiento')->assertOk()->json('data');

    expect(collect($filas)->where('alerta_reingreso', true))->toHaveCount(1)
        ->and(collect($filas)->firstWhere('alerta_reingreso', true)['hitos'][0]['respuestas'])->toBeGreaterThanOrEqual(3);
});

it('incluye una evaluación de impacto con costo cero y otras con ROI', function () {
    sembrarReportes($this);

    $filas = collect($this->getJson('/api/reportes/impactos?evaluado=con')->assertOk()->json('data'));

    expect($filas->count())->toBeGreaterThanOrEqual(2)
        ->and($filas->where('costo', 0)->pluck('roi')->all())->toBe([null])
        ->and($filas->whereNotNull('roi')->count())->toBeGreaterThanOrEqual(1);
});

it('deja un colaborador (Luis) con dos DNF y una brecha cerrada', function () {
    sembrarReportes($this);

    $r = $this->getJson('/api/reportes/dashboard')->assertOk();

    expect(DB::table('dnf')->count())->toBe(3)
        ->and($r->json('data.indicadores.brechas_cerradas.valor'))->toBeGreaterThanOrEqual(1)
        ->and($r->json('data.indicadores.satisfaccion.valor'))->not->toBeNull()
        ->and($r->json('data.indicadores.roi_estimado.valor'))->not->toBeNull();
});

it('es idempotente', function () {
    sembrarReportes($this);
    $encuestas = EncuestaSatisfaccionModel::query()->count();

    $this->seed(ReportesSeeder::class);

    expect(EncuestaSatisfaccionModel::query()->count())->toBe($encuestas)->and(DB::table('dnf')->count())->toBe(3);
});
