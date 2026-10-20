<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Domain\ValueObjects\CodigoActividad;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionCompetenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActividadesSeeder extends Seeder
{
    /**
     * Siembra seis actividades de demostración del tenant 1 sobre acciones de los planes Ejecutando y Cerrado de
     * PlanFormacionSeeder: los seis tipos y los cuatro estados calculados (Programada, En curso, Finalizada y Cancelada),
     * registros de las cuatro situaciones de asistencia (Pendiente, Presente, Ausente y Justificado), calificaciones de
     * 1 a 5 y una actividad Finalizada con asistencia pendiente. Idempotente: no hace nada si ya existen actividades.
     * Para cubrir Virtual y E-learning añade dos acciones marcadas «[Act]» al plan Ejecutando, sin modificar
     * PlanFormacionSeeder. Escribe directamente en las tablas porque es una siembra de demostración.
     * Depende de UsuariosInicialesSeeder, CompetenciasSeeder, PersonalSeeder y PlanFormacionSeeder.
     */
    public function run(): void
    {
        $tenantId = 1;
        if (DB::table('actividades')->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $ejecutando = PlanModel::query()->where('tenant_id', $tenantId)->where('estado', 'Ejecutando')->orderBy('id')->first();
        $cerrado = PlanModel::query()->where('tenant_id', $tenantId)->where('estado', 'Cerrado')->orderBy('id')->first();
        $competencias = array_values(array_map(fn (array $c) => (int) $c['id'], EstructuraFacade::getCompetenciasActivas()));
        $codesarrollo = $ejecutando === null ? null : PlanAccionModel::query()->where('plan_id', $ejecutando->id)->where('tipo', 'Codesarrollo')->first();
        if ($admin === null || $ejecutando === null || $cerrado === null || $codesarrollo === null || $competencias === []) {
            return;
        }
        $admin = (int) $admin;

        $presencial = PlanAccionModel::query()->where('plan_id', $cerrado->id)->where('tipo', 'Presencial')->first();
        $mentoring = PlanAccionModel::query()->where('plan_id', $ejecutando->id)->where('tipo', 'Mentoring')->first();
        $ojt = PlanAccionModel::query()->where('plan_id', $ejecutando->id)->where('tipo', 'OJT')->first();
        if ($presencial === null || $mentoring === null || $ojt === null) {
            return;
        }

        $participantes = PlanAccionParticipanteModel::query()->where('plan_accion_id', $codesarrollo->id)->orderBy('id')->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all();
        $hoy = CarbonImmutable::today();
        $anio = (int) $ejecutando->anio;
        $dentroDelAnio = fn (CarbonImmutable $f) => $f->year === $anio ? $f : CarbonImmutable::create($anio, $f->month > 6 ? 12 : 1, $f->month > 6 ? 31 : 1);

        DB::transaction(function () use ($tenantId, $admin, $ejecutando, $competencias, $codesarrollo, $presencial, $mentoring, $ojt, $participantes, $hoy, $dentroDelAnio) {
            $nueva = function (string $nombre, string $tipo, int $desde, int $hasta, float $horas, string $formador) use ($ejecutando, $admin, $competencias, $participantes, $hoy, $dentroDelAnio): PlanAccionModel {
                $accion = PlanAccionModel::query()->create([
                    'plan_id' => $ejecutando->id, 'nombre' => $nombre, 'tipo' => $tipo,
                    'fecha_inicio' => $dentroDelAnio($hoy->addDays($desde))->toDateString(), 'fecha_fin' => $dentroDelAnio($hoy->addDays($hasta))->toDateString(),
                    'duracion_horas' => $horas, 'formador' => $formador, 'capacidad_maxima' => 30, 'costo' => 0, 'cancelada' => false,
                    'created_by' => $admin, 'updated_by' => $admin,
                ]);
                PlanAccionCompetenciaModel::query()->create(['plan_accion_id' => $accion->id, 'competencia_id' => $competencias[0]]);
                foreach ($participantes as $colaboradorId) {
                    PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $accion->id, 'colaborador_id' => $colaboradorId, 'created_by' => $admin]);
                }

                return $accion;
            };
            $virtual = $nueva('[Act] Analítica con tableros', 'Virtual', -2, 8, 10, 'Mg. Andrés Molina');
            $elearning = $nueva('[Act] Autoaprendizaje en datos', 'ELearning', 5, 20, 6, 'Plataforma interna');

            $numero = 0;
            $actividad = function (PlanAccionModel $accion, string $lugar, ?int $departamento) use ($tenantId, $admin, &$numero): ActividadModel {
                $numero++;

                return ActividadModel::query()->create([
                    'tenant_id' => $tenantId, 'numero' => $numero, 'codigo' => CodigoActividad::desde($numero), 'plan_accion_id' => $accion->id,
                    'lugar_enlace' => $lugar, 'departamento_id' => $departamento, 'version' => 1, 'created_by' => $admin, 'updated_by' => $admin,
                ]);
            };
            $registro = function (ActividadModel $a, int $colaboradorId, string $asistencia, ?int $calificacion = null, ?string $observaciones = null) use ($admin): void {
                ActividadAsistenciaModel::query()->create([
                    'actividad_id' => $a->id, 'colaborador_id' => $colaboradorId, 'asistencia' => $asistencia,
                    'calificacion' => $calificacion, 'observaciones' => $observaciones, 'registrado_by' => $admin,
                ]);
                ActividadHistorialModel::query()->create([
                    'actividad_id' => $a->id, 'colaborador_id' => $colaboradorId, 'asistencia_anterior' => null, 'asistencia_nueva' => $asistencia,
                    'calificacion_anterior' => null, 'calificacion_nueva' => $calificacion, 'user_id' => $admin,
                ]);
            };
            [$p0, $p1, $p2] = array_pad($participantes, 3, $participantes[0]);

            // 1) Presencial · Finalizada · plan Cerrado: todos con registro y calificación.
            $a1 = $actividad($presencial, 'Sala de capacitación, piso 3', null);
            $inscritos = PlanAccionParticipanteModel::query()->where('plan_accion_id', $presencial->id)->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all();
            foreach ($inscritos as $i => $colaboradorId) {
                $registro($a1, $colaboradorId, 'Presente', 5 - ($i % 2), $i === 0 ? 'Excelente participación' : null);
            }

            // 2) Codesarrollo · En curso: Presente calificado, Ausente y Justificado.
            $a2 = $actividad($codesarrollo, 'Sala de colaboración, piso 2', null);
            $registro($a2, $p0, 'Presente', 4, 'Aporta casos reales');
            $registro($a2, $p1, 'Ausente', null, 'No avisó');
            $registro($a2, $p2, 'Justificado', null, 'Cita médica');

            // 3) Mentoring · Finalizada · sin registros: queda con «Asistencia pendiente».
            $actividad($mentoring, 'Oficina del mentor, piso 5', null);

            // 4) OJT · Programada.
            $actividad($ojt, 'Puesto de trabajo (Atención al cliente)', null);

            // 5) Virtual · En curso: un Presente sin calificar y el resto Pendiente.
            $a5 = $actividad($virtual, 'https://meet.ejemplo.edu.ec/tableros', null);
            $registro($a5, $p0, 'Presente');

            // 6) E-learning · se registra y luego la acción se cancela en el plan: conserva sus registros.
            $a6 = $actividad($elearning, 'https://aula.ejemplo.edu.ec/datos', null);
            $registro($a6, $p0, 'Presente', 3);
            PlanAccionModel::query()->whereKey($elearning->id)->update(['cancelada' => true, 'motivo_cancelacion' => 'El proveedor retiró el curso.']);
        });
    }
}
