<?php

use App\Modules\GestionPersonal\Application\Ports\LectorDeDesarrolloPort;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Database\Seeders\PersonalSeeder;
use Illuminate\Support\Facades\DB;

it('lee por lote sin N+1, con fichas vacías y sin datos de otros tenants', function () {
    $a = fichaDe();
    $b = fichaDe();
    $ajena = fichaDe([], 2);
    FormacionAcademicaModel::factory()->create(['colaborador_id' => $a->id]);
    ExperienciaAnteriorModel::factory()->count(2)->create(['colaborador_id' => $a->id]);
    FormacionAcademicaModel::factory()->create(['tenant_id' => 2, 'colaborador_id' => $ajena->id]);

    DB::enableQueryLog();
    $lectura = app(LectorDeDesarrolloPort::class)->deColaboradores(1, [$a->id, $b->id, $ajena->id]);
    $consultas = count(DB::getQueryLog());

    expect($lectura->estaDisponible())->toBeTrue()
        ->and($consultas)->toBeLessThanOrEqual(3)
        ->and(array_keys($lectura->datos))->toEqualCanonicalizing([$a->id, $b->id])
        ->and($lectura->datos[$a->id]['formaciones'])->toHaveCount(1)
        ->and($lectura->datos[$a->id]['experiencias'])->toHaveCount(2)
        ->and($lectura->datos[$a->id]['formaciones'][0])->not->toHaveKeys(['version', 'created_at'])
        ->and($lectura->datos[$b->id])->toBe(['formaciones' => [], 'experiencias' => [], 'movilidad' => ['estado' => 'NoInformada', 'observaciones' => null, 'actualizada_at' => null]]);
});

it('la fachada devuelve los datos y degrada con la excepción del módulo si falla', function () {
    $a = fichaDe();
    expect(PersonalFacade::getDesarrollo(1, [$a->id]))->toHaveKey($a->id);

    app()->bind(LectorDeDesarrolloPort::class, fn () => new class implements LectorDeDesarrolloPort
    {
        public function deColaboradores(int $tenantId, array $colaboradorIds): Lectura
        {
            return Lectura::noDisponible();
        }
    });
    app()->forgetInstance('personal-facade');
    PersonalFacade::clearResolvedInstance('personal-facade');

    expect(fn () => PersonalFacade::getDesarrollo(1, [$a->id]))->toThrow(InformacionNoDisponible::class);
});

it('el seeder de personal carga ejemplos de desarrollo en los tres estados de movilidad', function () {
    config(['hrms.seed_password' => 'secreto-de-prueba-123']);
    $this->seed(PersonalSeeder::class);

    $estados = ColaboradorModel::query()->where('tenant_id', 1)->pluck('movilidad_estado')->unique()->all();

    expect($estados)->toContain('Disponible', 'NoDisponible', 'NoInformada')
        ->and(FormacionAcademicaModel::query()->pluck('estado')->unique()->all())->toContain('EnCurso', 'Completada', 'Incompleta')
        ->and(ExperienciaAnteriorModel::query()->whereNull('fecha_finalizacion')->exists())->toBeTrue();
});
