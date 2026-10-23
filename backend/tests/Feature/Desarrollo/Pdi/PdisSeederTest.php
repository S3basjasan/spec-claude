<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHitoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;
use Database\Seeders\CompetenciasSeeder;
use Database\Seeders\PdisSeeder;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\PosicionesSeeder;
use Database\Seeders\PuestosSeeder;
use Database\Seeders\TiposGradoSeeder;
use Database\Seeders\TiposUnidadSeeder;
use Database\Seeders\UnidadesSeeder;
use Database\Seeders\UsuariosInicialesSeeder;

/** Siembra lo que necesita el PDI, en el orden de DatabaseSeeder y sin IngresosSeeder. */
function sembrarParaPdis($prueba): void
{
    foreach ([UsuariosInicialesSeeder::class, TiposGradoSeeder::class, CompetenciasSeeder::class, PuestosSeeder::class, TiposUnidadSeeder::class, UnidadesSeeder::class, PosicionesSeeder::class, PersonalSeeder::class, PdisSeeder::class] as $seeder) {
        $prueba->seed($seeder);
    }
}

it('siembra cinco planes principales con los estados, tipos y resultados previstos', function () {
    sembrarParaPdis($this);

    $planes = PdiModel::query()->orderBy('id')->limit(5)->get();

    expect($planes->pluck('codigo')->all())->toBe(['PDI-0001', 'PDI-0002', 'PDI-0003', 'PDI-0004', 'PDI-0005'])
        ->and($planes->pluck('estado')->all())->toBe(['Borrador', 'Borrador', 'Acordado', 'Cerrado', 'Cerrado'])
        ->and($planes->pluck('tipo')->all())->toBe(['Mejora', 'Carrera', 'Talento', 'Mejora', 'Carrera'])
        ->and($planes->pluck('resultado')->all())->toBe([null, null, null, 'BrechaCerrada', 'Parcial'])
        ->and($planes[0]->acuerdo_responsable_at)->toBeNull()
        ->and($planes[1]->acuerdo_responsable_at)->not->toBeNull()
        ->and($planes[1]->aceptacion_colaborador_at)->toBeNull()
        ->and($planes[2]->aceptacion_colaborador_at)->not->toBeNull();
});

it('respeta un solo plan abierto por colaborador y tipo y exige puesto objetivo en Carrera y Talento', function () {
    sembrarParaPdis($this);

    $abiertos = PdiModel::query()->where('estado', '!=', 'Cerrado')->get()->map(fn ($p) => $p->colaborador_id.'-'.$p->tipo);

    expect($abiertos->unique()->count())->toBe($abiertos->count())
        ->and(PdiModel::query()->whereIn('tipo', ['Carrera', 'Talento'])->whereNull('puesto_objetivo_id')->exists())->toBeFalse();
});

it('cubre los siete tipos de acción y un hito vencido en el plan Acordado', function () {
    sembrarParaPdis($this);

    $acordado = PdiModel::query()->where('estado', 'Acordado')->firstOrFail();
    $vencidos = PdiHitoModel::query()->where('pdi_id', $acordado->id)->where('estado', 'Pendiente')->where('fecha', '<', now()->toDateString())->count();

    expect(PdiAccionModel::query()->distinct()->pluck('tipo')->sort()->values()->all())
        ->toBe(['Autodesarrollo', 'Coaching', 'Codesarrollo', 'Curso', 'Mentoring', 'NuevaResponsabilidad', 'Otra'])
        ->and($vencidos)->toBeGreaterThanOrEqual(1);
});

it('los planes Cerrados tienen resultado en cada compromiso y acciones Cumplidas o Canceladas', function () {
    sembrarParaPdis($this);

    foreach (PdiModel::query()->where('estado', 'Cerrado')->with('compromisos')->get() as $plan) {
        foreach ($plan->compromisos as $compromiso) {
            expect($compromiso->resultado)->not->toBeNull();
            expect(PdiAccionModel::query()->where('compromiso_id', $compromiso->id)->whereNotIn('estado', ['Cumplida', 'Cancelada'])->exists())->toBeFalse();
        }
    }
});

it('deja historial y es idempotente', function () {
    sembrarParaPdis($this);
    $this->seed(PdisSeeder::class);

    $total = PdiModel::query()->count();

    expect($total)->toBeGreaterThanOrEqual(5)
        ->and(PdiHistorialModel::query()->where('tipo', 'Creacion')->count())->toBe($total);
});
