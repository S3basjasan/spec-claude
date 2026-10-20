<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionCompetenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportesSeeder extends Seeder
{
    /**
     * Siembra datos de demostración de Reportes del tenant 1 sobre las actividades Finalizadas: encuestas de satisfacción,
     * seguimientos de los tres hitos (con un caso de alerta de reingreso), evaluaciones de impacto (una con costo cero) y
     * una segunda DNF de Luis Núñez que cierra una brecha. Idempotente: no hace nada si ya hay encuestas, seguimientos o
     * evaluaciones. Escribe directamente en las tablas porque es una siembra de demostración (las ventanas de respuesta
     * solo rigen las respuestas por la API). Añade dos acciones marcadas «[Rep]» al plan Ejecutando sin modificar los
     * seeders anteriores.
     * Depende de UsuariosInicialesSeeder, CompetenciasSeeder, PersonalSeeder, DnfSeeder, PlanFormacionSeeder y ActividadesSeeder.
     */
    public function run(): void
    {
        $tenantId = 1;
        if (DB::table('encuestas_satisfaccion')->exists() || DB::table('seguimientos_impacto')->exists() || DB::table('impactos_accion')->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $ejecutando = PlanModel::query()->where('tenant_id', $tenantId)->where('estado', 'Ejecutando')->orderBy('id')->first();
        $cerrado = PlanModel::query()->where('tenant_id', $tenantId)->where('estado', 'Cerrado')->orderBy('id')->first();
        $competencias = array_values(array_map(fn (array $c) => (int) $c['id'], EstructuraFacade::getCompetenciasActivas()));
        $ana = PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0102030405');
        // Los datos de Ana quedan intactos: otras pruebas de extremo a extremo dependen de su plan y de su diagnóstico.
        $fichas = DB::table('colaboradores')->where('tenant_id', $tenantId)->when($ana !== null, fn ($q) => $q->where('id', '<>', $ana['id']))->orderBy('id')->limit(4)->pluck('id')->map(fn ($i) => (int) $i)->all();
        if ($admin === null || $ejecutando === null || $cerrado === null || $competencias === [] || count($fichas) < 3) {
            return;
        }
        $admin = (int) $admin;
        $anio = (int) $ejecutando->anio;

        DB::transaction(function () use ($tenantId, $admin, $ejecutando, $cerrado, $competencias, $fichas, $anio, $ana) {
            $accion = function (string $nombre, string $tipo, float $costo, float $horas) use ($ejecutando, $admin, $competencias, $fichas, $anio): PlanAccionModel {
                $a = PlanAccionModel::query()->create([
                    'plan_id' => $ejecutando->id, 'nombre' => $nombre, 'tipo' => $tipo,
                    'fecha_inicio' => CarbonImmutable::create($anio, 1, 5)->toDateString(), 'fecha_fin' => CarbonImmutable::create($anio, 1, 20)->toDateString(),
                    'duracion_horas' => $horas, 'formador' => 'Equipo interno', 'capacidad_maxima' => 30, 'costo' => $costo, 'cancelada' => false,
                    'created_by' => $admin, 'updated_by' => $admin,
                ]);
                PlanAccionCompetenciaModel::query()->create(['plan_accion_id' => $a->id, 'competencia_id' => $competencias[0]]);
                foreach ($fichas as $colaboradorId) {
                    PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $a->id, 'colaborador_id' => $colaboradorId, 'created_by' => $admin]);
                }

                return $a;
            };
            $reingreso = $accion('[Rep] Taller de reingreso: aplicar lo aprendido', 'Presencial', 1500, 12);
            $guia = $accion('[Rep] Guía OJT sin costo', 'OJT', 0, 8);

            $numero = (int) ActividadModel::query()->where('tenant_id', $tenantId)->max('numero');
            $numero++;
            $actividad = ActividadModel::query()->create([
                'tenant_id' => $tenantId, 'numero' => $numero, 'codigo' => CodigoActividad::desde($numero), 'plan_accion_id' => $reingreso->id,
                'lugar_enlace' => 'Sala de capacitación, piso 3', 'departamento_id' => null, 'version' => 1, 'created_by' => $admin, 'updated_by' => $admin,
            ]);
            foreach ($fichas as $i => $colaboradorId) {
                ActividadAsistenciaModel::query()->create([
                    'actividad_id' => $actividad->id, 'colaborador_id' => $colaboradorId, 'asistencia' => 'Presente', 'calificacion' => 4 + ($i % 2),
                    'observaciones' => null, 'registrado_by' => $admin,
                ]);
            }

            // Actividad Finalizada del plan Cerrado (sembrada por ActividadesSeeder): su acción Presencial.
            $delCerrado = ActividadModel::query()->where('tenant_id', $tenantId)
                ->whereIn('plan_accion_id', PlanAccionModel::query()->where('plan_id', $cerrado->id)->pluck('id'))->orderBy('id')->first();

            $encuesta = function (int $actividadId, int $colaboradorId, int $calificacion, ?string $comentario) use ($tenantId): void {
                EncuestaSatisfaccionModel::query()->create([
                    'tenant_id' => $tenantId, 'actividad_id' => $actividadId, 'colaborador_id' => $colaboradorId, 'calificacion' => $calificacion, 'comentario' => $comentario,
                ]);
            };
            $seguimiento = function (int $actividadId, int $colaboradorId, int $hito, int $aplicacion, int $apoyo, ?string $comentario = null) use ($tenantId): void {
                SeguimientoImpactoModel::query()->create([
                    'tenant_id' => $tenantId, 'actividad_id' => $actividadId, 'colaborador_id' => $colaboradorId, 'hito' => $hito,
                    'aplicacion' => $aplicacion, 'apoyo' => $apoyo, 'comentario' => $comentario,
                ]);
            };

            $comentarios = ['Los ejemplos fueron muy útiles.', null, 'Faltó tiempo para practicar.', 'Excelente facilitador.'];
            foreach ($fichas as $i => $colaboradorId) {
                $encuesta($actividad->id, $colaboradorId, [5, 4, 3, 5][$i] ?? 4, $comentarios[$i] ?? null);
            }

            // Caso de alerta de reingreso: el promedio de aplicación cae 1 punto entre los hitos 30 y 60, con 3 o más respuestas.
            foreach (array_slice($fichas, 0, 4) as $i => $colaboradorId) {
                $seguimiento($actividad->id, $colaboradorId, 30, [5, 5, 4, 5][$i] ?? 5, [4, 4, 3, 4][$i] ?? 4, $i === 0 ? 'Mi equipo me apoya.' : null);
                $seguimiento($actividad->id, $colaboradorId, 60, [4, 4, 3, 4][$i] ?? 4, [3, 3, 2, 3][$i] ?? 3);
            }
            foreach (array_slice($fichas, 0, 3) as $i => $colaboradorId) {
                $seguimiento($actividad->id, $colaboradorId, 90, [3, 3, 4][$i], [2, 3, 3][$i], $i === 0 ? 'El entorno volvió a la rutina anterior.' : null);
            }

            if ($delCerrado !== null) {
                $inscritos = PlanAccionParticipanteModel::query()->where('plan_accion_id', $delCerrado->plan_accion_id)->orderBy('id')->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all();
                foreach (array_values(array_filter($inscritos, fn (int $id) => $ana === null || $id !== (int) $ana['id'])) as $i => $colaboradorId) {
                    $encuesta((int) $delCerrado->id, $colaboradorId, 4 + ($i % 2), $i === 0 ? 'Muy buena comunicación entre áreas.' : null);
                    $seguimiento((int) $delCerrado->id, $colaboradorId, 30, 4, 4);
                }
                ImpactoAccionModel::query()->create([
                    'tenant_id' => $tenantId, 'plan_accion_id' => $delCerrado->plan_accion_id, 'indicador' => 'Quejas internas por mes', 'unidad' => 'quejas',
                    'valor_antes' => 14, 'valor_despues' => 9, 'beneficio_estimado' => 1250, 'justificacion' => 'Menos reprocesos por mala comunicación entre áreas.',
                    'created_by' => $admin, 'updated_by' => $admin,
                ]);
            }

            ImpactoAccionModel::query()->create([
                'tenant_id' => $tenantId, 'plan_accion_id' => $reingreso->id, 'indicador' => 'Tiempo de cierre contable', 'unidad' => 'días',
                'valor_antes' => 10, 'valor_despues' => 7, 'beneficio_estimado' => 4200, 'justificacion' => 'Menos horas extra al cierre de mes.',
                'created_by' => $admin, 'updated_by' => $admin,
            ]);
            // Acción de costo cero: su ROI no aplica.
            ImpactoAccionModel::query()->create([
                'tenant_id' => $tenantId, 'plan_accion_id' => $guia->id, 'indicador' => 'Errores de digitación', 'unidad' => 'errores',
                'valor_antes' => 30, 'valor_despues' => 12, 'beneficio_estimado' => 800, 'justificacion' => 'Guía interna que reduce la corrección de datos.',
                'created_by' => $admin, 'updated_by' => $admin,
            ]);
        });

        $this->segundaDnfDeLuis($tenantId, $admin, $competencias);
    }

    /**
     * Luis Núñez fue evaluado con la competencia crítica en nivel C (requerido A) en la DNF masiva de DnfSeeder; una evaluación
     * posterior la deja en nivel A: la brecha figura cerrada. Se omite si no hay exactamente las DNF de DnfSeeder.
     *
     * @param  list<int>  $competencias
     */
    private function segundaDnfDeLuis(int $tenantId, int $admin, array $competencias): void
    {
        $luis = PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0912345678');
        $puestos = EstructuraFacade::getPuestosActivos();
        if ($luis === null || $puestos === [] || count($competencias) < 6 || DB::table('dnf')->where('tenant_id', $tenantId)->count() !== 2) {
            return;
        }

        // Las competencias se ordenan igual que en DnfSeeder (las que tienen oferta formativa primero) para reutilizar la misma.
        $todas = array_values(EstructuraFacade::getCompetenciasActivas());
        usort($todas, fn (array $a, array $b) => DesarrolloFacade::competenciaTieneOferta((int) $b['id']) <=> DesarrolloFacade::competenciaTieneOferta((int) $a['id']));
        $critica = (int) $todas[0]['id'];

        $calculador = new CalculadorDeBrecha;
        app(DnfRepositoryInterface::class)->guardar(new Dnf(
            null, $tenantId, null, AlcanceDnf::Individual, null, 0, null, null, null, $admin, null,
            [new ResultadoColaborador((int) $luis['id'], (int) $puestos[0]['id'], [
                $calculador->calcular($critica, TipoCompetenciaDnf::Cardinal, NivelGrado::from('A'), NivelGrado::from('A')),
            ])],
        ));
    }
}
