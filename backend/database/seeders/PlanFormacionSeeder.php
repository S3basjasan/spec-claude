<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Domain\Services\NormalizadorDeNombre;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoPlan;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionCompetenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanFormacionSeeder extends Seeder
{
    /**
     * Siembra cinco planes de demostración del tenant 1, uno en cada estado, con acciones de los seis tipos, una
     * acción cancelada y acciones cuyo estado calculado es Programada, En curso y Finalizada respecto a hoy
     * (idempotente: no hace nada si ya existen planes). El Borrador tiene acciones incompletas, como las que crea la DNF.
     * Escribe directamente en las tablas del módulo porque es una siembra de demostración.
     * Depende de UsuariosInicialesSeeder, CompetenciasSeeder, CatalogoFormativoSeeder (cursos) y PersonalSeeder.
     */
    public function run(): void
    {
        $tenantId = 1;
        if (DB::table('planes')->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $competencias = array_values(array_map(fn (array $c) => (int) $c['id'], EstructuraFacade::getCompetenciasActivas()));
        $fichas = array_values(array_filter([
            PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0102030405'),
            PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0912345678'),
            PersonalFacade::buscarPorIdentificacion($tenantId, 'Pasaporte', 'AB123'),
        ]));
        if ($admin === null || count($competencias) < 3 || count($fichas) < 3) {
            return;
        }

        $admin = (int) $admin;
        $personas = array_map(fn (array $f) => (int) $f['id'], $fichas);
        $curso = CursoModel::query()->where('estado', 'Disponible')->value('id');
        $curso = $curso === null ? null : (int) $curso;
        $dnf = DB::table('dnf')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        $anio = (int) date('Y');
        $hoy = CarbonImmutable::today();
        $enAnio = function (CarbonImmutable $fecha, int $delAnio) {
            $minimo = CarbonImmutable::create($delAnio, 1, 1);
            $maximo = CarbonImmutable::create($delAnio, 12, 31);

            return $fecha->lessThan($minimo) ? $minimo : ($fecha->greaterThan($maximo) ? $maximo : $fecha);
        };
        // Fechas relativas a hoy, acotadas al año del plan para respetar la regla de fechas dentro del año.
        $rango = fn (int $desde, int $hasta, int $delAnio = 0) => [
            $enAnio($hoy->addDays($desde), $delAnio ?: $anio)->toDateString(),
            $enAnio($hoy->addDays($hasta), $delAnio ?: $anio)->toDateString(),
        ];
        $pasado = $rango(-40, -30);
        $curso_ = $rango(-3, 10);
        $futuro = $rango(30, 40);
        $anterior = [CarbonImmutable::create($anio - 1, 3, 2)->toDateString(), CarbonImmutable::create($anio - 1, 3, 6)->toDateString()];

        $plan = fn (int $numero, string $nombre, int $delAnio, string $estado, ?int $aprobador, ?int $origen, ?string $aprobadoEn) => PlanModel::query()->create([
            'tenant_id' => $tenantId,
            'numero' => $numero,
            'codigo' => CodigoPlan::desde($numero),
            'nombre' => $nombre,
            'nombre_normalizado' => (new NormalizadorDeNombre)->normalizar($nombre),
            'anio' => $delAnio,
            'descripcion' => null,
            'estado' => $estado,
            'aprobador_id' => $aprobador,
            'origen_dnf_id' => $origen,
            'fecha_aprobacion' => $aprobadoEn,
            'created_by' => $admin,
            'updated_by' => $admin,
        ]);

        DB::transaction(function () use ($plan, $admin, $competencias, $personas, $curso, $dnf, $anio, $pasado, $curso_, $futuro, $anterior, $hoy) {
            $c = $competencias;
            $p = $personas;

            // 1) Borrador con acciones incompletas, como las que crea la DNF.
            $borrador = $plan(1, "Plan DNF DNF-0001 ({$anio})", $anio, 'Borrador', null, $dnf === null ? null : (int) $dnf, null);
            $this->accion($borrador, $admin, ['nombre' => 'Orientación a resultados'], [$c[0]], [$p[0], $p[1]]);
            $this->accion($borrador, $admin, ['nombre' => 'Trabajo en equipo'], [$c[1]], [$p[1], $p[2]]);

            // 2) En revisión con una acción completa.
            $revision = $plan(2, "Plan de liderazgo {$anio}", $anio, 'Revision', $admin, null, null);
            $this->accion($revision, $admin, $this->completa('Taller de liderazgo situacional', 'Presencial', $futuro, 16, 15, 1200, 'Ps. Marco Salazar') + ['curso_id' => $curso], [$c[0]], [$p[0], $p[1]]);
            $this->historial($revision, $admin, 'Borrador', 'Revision');

            // 3) Aprobado: acciones programadas y una cancelada.
            $aprobado = $plan(3, "Plan técnico {$anio}", $anio, 'Aprobado', $admin, null, $hoy->subDays(5)->toDateTimeString());
            $this->accion($aprobado, $admin, $this->completa('Analítica con SQL', 'Virtual', $futuro, 24, 20, 900, 'Ing. Lucía Vera'), [$c[1]], [$p[0], $p[2]]);
            $this->accion($aprobado, $admin, $this->completa('Autoaprendizaje en datos', 'ELearning', $futuro, 30, 50, 400, 'Plataforma interna'), [$c[2]], [$p[1]]);
            $cancelada = $this->accion($aprobado, $admin, $this->completa('Guía OJT de tableros', 'OJT', $futuro, 8, 4, 0, 'Mg. Andrés Molina'), [$c[1]], [$p[0]]);
            PlanAccionModel::query()->whereKey($cancelada)->update(['cancelada' => true, 'motivo_cancelacion' => 'El formador no está disponible.']);
            $this->historial($aprobado, $admin, 'Borrador', 'Revision', null, 6);
            $this->historial($aprobado, $admin, 'Revision', 'Aprobado', null, 5);
            PlanHistorialModel::query()->create(['plan_id' => $aprobado->id, 'plan_accion_id' => $cancelada, 'tipo' => 'Cancelacion', 'valor_anterior' => null, 'valor_nuevo' => 'Cancelada', 'motivo' => 'El formador no está disponible.', 'user_id' => $admin]);

            // 4) Ejecutando: una acción en curso, una finalizada y una programada.
            $ejecutando = $plan(4, "Plan de desarrollo {$anio}", $anio, 'Ejecutando', $admin, null, $hoy->subDays(30)->toDateTimeString());
            $this->accion($ejecutando, $admin, $this->completa('Codesarrollo: casos reales de equipo', 'Codesarrollo', $curso_, 9, 12, 300, 'Ps. Marco Salazar'), [$c[2]], [$p[0], $p[1], $p[2]]);
            $this->accion($ejecutando, $admin, $this->completa('Mentoring para nuevos líderes', 'Mentoring', $pasado, 12, 6, 600, 'Dra. Paula Ortiz'), [$c[0]], [$p[1]]);
            $this->accion($ejecutando, $admin, $this->completa('Guía OJT de atención', 'OJT', $futuro, 10, 8, 150, 'Mg. Daniela Cruz'), [$c[1]], [$p[2]]);
            $this->historial($ejecutando, $admin, 'Borrador', 'Revision', null, 35);
            $this->historial($ejecutando, $admin, 'Revision', 'Aprobado', null, 30);
            $this->historial($ejecutando, $admin, 'Aprobado', 'Ejecutando', null, 29);

            // 5) Cerrado (año anterior): todas las acciones finalizadas.
            $cerrado = $plan(5, 'Plan de formación '.($anio - 1), $anio - 1, 'Cerrado', $admin, null, $hoy->subYear()->toDateTimeString());
            $this->accion($cerrado, $admin, $this->completa('Comunicación asertiva en el trabajo', 'Presencial', $anterior, 8, 20, 500, 'Ps. Marco Salazar'), [$c[0]], [$p[0], $p[1]]);
            foreach ([['Borrador', 'Revision'], ['Revision', 'Aprobado'], ['Aprobado', 'Ejecutando'], ['Ejecutando', 'Cerrado']] as $i => [$de, $a]) {
                $this->historial($cerrado, $admin, $de, $a, null, 400 - $i);
            }
        });
    }

    /**
     * @param  array{0: string, 1: string}  $fechas
     * @return array<string, mixed>
     */
    private function completa(string $nombre, string $tipo, array $fechas, float $horas, int $capacidad, float $costo, string $formador): array
    {
        return [
            'nombre' => $nombre, 'tipo' => $tipo, 'fecha_inicio' => $fechas[0], 'fecha_fin' => $fechas[1],
            'duracion_horas' => $horas, 'capacidad_maxima' => $capacidad, 'costo' => $costo, 'formador' => $formador,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $competencias
     * @param  list<int>  $participantes
     */
    private function accion(PlanModel $plan, int $admin, array $datos, array $competencias, array $participantes): int
    {
        $accion = PlanAccionModel::query()->create($datos + ['plan_id' => $plan->id, 'created_by' => $admin, 'updated_by' => $admin]);
        foreach ($competencias as $competenciaId) {
            PlanAccionCompetenciaModel::query()->create(['plan_accion_id' => $accion->id, 'competencia_id' => $competenciaId]);
        }
        foreach ($participantes as $colaboradorId) {
            PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $accion->id, 'colaborador_id' => $colaboradorId, 'created_by' => $admin]);
        }

        return (int) $accion->id;
    }

    private function historial(PlanModel $plan, int $admin, string $de, string $a, ?string $motivo = null, int $haceDias = 0): void
    {
        $renglon = PlanHistorialModel::query()->create([
            'plan_id' => $plan->id, 'plan_accion_id' => null, 'tipo' => 'Estado', 'valor_anterior' => $de, 'valor_nuevo' => $a, 'motivo' => $motivo, 'user_id' => $admin,
        ]);
        if ($haceDias > 0) {
            PlanHistorialModel::query()->whereKey($renglon->id)->toBase()->update(['created_at' => CarbonImmutable::now()->subDays($haceDias)]);
        }
    }
}
