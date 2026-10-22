<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Database\Seeders\AdecuacionesSeeder;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\PosicionesSeeder;
use Database\Seeders\PuestosSeeder;
use Database\Seeders\RutasSeeder;
use Database\Seeders\TiposUnidadSeeder;
use Database\Seeders\UnidadesSeeder;
use Database\Seeders\UsuariosInicialesSeeder;
use Tests\Support\Desarrollo\ColaboradoresVigentesFalso;

/** Siembra lo que necesita la adecuación, en el orden de DatabaseSeeder y sin IngresosSeeder. */
function sembrarParaAdecuaciones($prueba): void
{
    foreach ([UsuariosInicialesSeeder::class, PuestosSeeder::class, TiposUnidadSeeder::class, UnidadesSeeder::class, PosicionesSeeder::class, PersonalSeeder::class] as $seeder) {
        $prueba->seed($seeder);
    }
    $ids = [];
    foreach (['Cedula:0102030405', 'Cedula:0912345678', 'Pasaporte:AB123'] as $clave) {
        [$tipo, $numero] = explode(':', $clave);
        $ficha = PersonalFacade::buscarPorIdentificacion(1, $tipo, $numero);
        $ids[(int) $ficha['id']] = trim($ficha['nombres'].' '.$ficha['apellidos']);
    }
    app()->instance(ColaboradoresVigentesInterface::class, new ColaboradoresVigentesFalso($ids));
    $prueba->seed(RutasSeeder::class);
    $prueba->seed(AdecuacionesSeeder::class);
}

it('siembra cuatro adecuaciones que cubren las cuatro preparaciones', function () {
    sembrarParaAdecuaciones($this);

    $adecuaciones = AdecuacionModel::query()->orderBy('id')->get();

    expect($adecuaciones->pluck('codigo')->all())->toBe(['ADE-0001', 'ADE-0002', 'ADE-0003', 'ADE-0004'])
        ->and($adecuaciones->pluck('preparacion')->sort()->values()->all())->toBe(['EnDesarrollo', 'Incompleta', 'Lista', 'NoLista'])
        ->and($adecuaciones->every(fn ($a) => (int) $a->umbral_meses === 12))->toBeTrue();
});

it('deja un puesto objetivo con tres candidatos y una adecuación por puesto directo', function () {
    sembrarParaAdecuaciones($this);

    $porPuesto = AdecuacionModel::query()->get()->groupBy('puesto_objetivo_id');
    $conTres = $porPuesto->filter(fn ($g) => $g->pluck('colaborador_id')->unique()->count() === 3);

    expect($conTres)->toHaveCount(1)
        ->and(AdecuacionModel::query()->whereNull('inscripcion_id')->count())->toBeGreaterThanOrEqual(1)
        ->and(AdecuacionModel::query()->whereNotNull('inscripcion_id')->count())->toBeGreaterThanOrEqual(1);
});

it('las adecuaciones sembradas son coherentes con su preparación y tiempos de cierre', function () {
    sembrarParaAdecuaciones($this);

    foreach (repositorioDeAdecuacionesSembradas() as $a) {
        foreach ($a->resultados as $r) {
            expect($r->esBrecha())->toBe($r->tiempoCierreMeses !== null);
        }
        $brechas = $a->brechas();
        match ($a->preparacion->value) {
            'Lista' => expect($brechas)->toBe([]),
            'EnDesarrollo' => expect(max(array_map(fn ($b) => $b->tiempoCierreMeses, $brechas)))->toBeLessThanOrEqual(12),
            'NoLista' => expect(max(array_map(fn ($b) => $b->tiempoCierreMeses, $brechas)))->toBeGreaterThan(12),
            'Incompleta' => expect(collect($a->resultados)->contains(fn ($r) => $r->estado->value === 'SinEvaluacion'))->toBeTrue(),
        };
    }
});

it('es idempotente', function () {
    sembrarParaAdecuaciones($this);
    $this->seed(AdecuacionesSeeder::class);

    expect(AdecuacionModel::query()->count())->toBe(4);
});

function repositorioDeAdecuacionesSembradas(): array
{
    return app(AdecuacionesRepositoryInterface::class)->vigentes(1);
}
