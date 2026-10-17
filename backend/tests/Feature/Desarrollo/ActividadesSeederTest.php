<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use Database\Seeders\ActividadesSeeder;
use Database\Seeders\CatalogoFormativoSeeder;
use Database\Seeders\CompetenciasSeeder;
use Database\Seeders\DnfSeeder;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\PlanFormacionSeeder;
use Database\Seeders\PosicionesSeeder;
use Database\Seeders\PuestosSeeder;
use Database\Seeders\TiposUnidadSeeder;
use Database\Seeders\UnidadesSeeder;
use Database\Seeders\UsuariosInicialesSeeder;

/**
 * Siembra lo que necesitan los planes y las actividades, en el orden de DatabaseSeeder y sin IngresosSeeder (que no
 * interviene en estas actividades).
 */
function sembrarParaActividades($prueba): void
{
    foreach ([UsuariosInicialesSeeder::class, CompetenciasSeeder::class, PuestosSeeder::class, CatalogoFormativoSeeder::class, TiposUnidadSeeder::class,
        UnidadesSeeder::class, PosicionesSeeder::class, PersonalSeeder::class, DnfSeeder::class, PlanFormacionSeeder::class, ActividadesSeeder::class] as $seeder) {
        $prueba->seed($seeder);
    }
}

it('siembra seis actividades que cubren los seis tipos y los cuatro estados calculados', function () {
    sembrarParaActividades($this);
    actividadFalsos(['hoy' => now()->toDateString()]);
    actuarComoAdminDeTenant();

    expect(ActividadModel::query()->orderBy('numero')->pluck('codigo')->all())->toBe(['ACT-0001', 'ACT-0002', 'ACT-0003', 'ACT-0004', 'ACT-0005', 'ACT-0006']);

    $r = $this->getJson('/api/actividades?fecha_desde=2000-01-01&fecha_hasta=2100-12-31')->assertOk();
    expect(collect($r->json('data'))->pluck('tipo')->unique()->sort()->values()->all())->toBe(['Codesarrollo', 'ELearning', 'Mentoring', 'OJT', 'Presencial', 'Virtual'])
        ->and(collect($r->json('data'))->pluck('estado')->unique()->sort()->values()->all())->toBe(['Cancelada', 'EnCurso', 'Finalizada', 'Programada']);
});

it('incluye registros de las cuatro situaciones, calificaciones de 1 a 5 y una Finalizada con asistencia pendiente', function () {
    sembrarParaActividades($this);
    actividadFalsos(['hoy' => now()->toDateString()]);
    actuarComoAdminDeTenant();

    expect(ActividadAsistenciaModel::query()->pluck('asistencia')->unique()->sort()->values()->all())->toBe(['Ausente', 'Justificado', 'Presente'])
        ->and(ActividadAsistenciaModel::query()->whereNotNull('calificacion')->pluck('calificacion')->unique()->every(fn ($n) => $n >= 1 && $n <= 5))->toBeTrue()
        ->and(ActividadAsistenciaModel::query()->whereNotNull('calificacion')->count())->toBeGreaterThan(0)
        ->and(ActividadHistorialModel::query()->count())->toBe(ActividadAsistenciaModel::query()->count());

    $r = $this->getJson('/api/actividades?fecha_desde=2000-01-01&fecha_hasta=2100-12-31')->assertOk();
    $pendientes = collect($r->json('data'))->where('asistencia_pendiente', true);
    expect($pendientes)->not->toBeEmpty()->and($pendientes->pluck('estado')->unique()->all())->toBe(['Finalizada']);

    $r2 = $this->getJson('/api/actividades/'.ActividadModel::query()->where('numero', 1)->value('id'))->assertOk();
    expect(collect($r2->json('data.participantes'))->pluck('asistencia')->unique()->all())->toBe(['Presente']);
    $detalle = $this->getJson('/api/actividades/'.ActividadModel::query()->where('numero', 5)->value('id'))->assertOk();
    expect(collect($detalle->json('data.participantes'))->pluck('asistencia')->unique()->sort()->values()->all())->toBe(['Pendiente', 'Presente']);
});

it('es idempotente: sembrar dos veces no duplica las actividades', function () {
    sembrarParaActividades($this);
    $this->seed(ActividadesSeeder::class);

    expect(ActividadModel::query()->count())->toBe(6);
});
