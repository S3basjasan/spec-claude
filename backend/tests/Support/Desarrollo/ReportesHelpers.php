<?php

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use Illuminate\Support\Facades\DB;

/**
 * Helpers de la feature 018 (Reportes de evaluación). Datos sintéticos; se apoyan en los de las features 013 y 017.
 */

/**
 * Departamento actual de cada colaborador (colaborador_id => unidad_id|null); el resto figura sin departamento.
 *
 * @param  array<int, int|null>  $mapa
 */
function departamentosActuales(array $mapa): void
{
    app()->instance(DepartamentoDeColaboradorInterface::class, new class($mapa) implements DepartamentoDeColaboradorInterface
    {
        /** @param  array<int, int|null>  $mapa */
        public function __construct(private readonly array $mapa) {}

        public function actuales(int $tenantId, array $colaboradorIds, string $fecha): array
        {
            $r = [];
            foreach ($colaboradorIds as $id) {
                $r[$id] = $this->mapa[$id] ?? null;
            }

            return $r;
        }
    });
}

/** Fuente de departamentos que siempre falla (simula Gestión de Personal caída). */
function departamentosActualesCaidos(): void
{
    app()->instance(DepartamentoDeColaboradorInterface::class, new class implements DepartamentoDeColaboradorInterface
    {
        public function actuales(int $tenantId, array $colaboradorIds, string $fecha): array
        {
            throw new RuntimeException('Gestión de Personal no disponible');
        }
    });
}

/**
 * Crea un plan Ejecutando con una acción y su actividad, y registra la asistencia de los colaboradores.
 * Con `hoy` por defecto de `actividadFalsos` (2026-03-05) la acción por defecto (2026-03-02 a 2026-03-13) está En curso;
 * para Finalizada llame a `actividadFalsos(['hoy' => '2026-03-20', ...])` antes.
 *
 * @param  array<int, string>  $asistencia  colaborador_id => Presente|Ausente|Justificado
 * @param  array<int, int|null>  $calificaciones  colaborador_id => calificación 1-5
 * @param  array<string, mixed>  $accion  cambios sobre la acción por defecto
 * @return array{plan: int, accion: int, actividad: int}
 */
function actividadConAsistencia(array $asistencia, array $accion = [], array $calificaciones = [], ?int $departamentoId = null, string $estadoPlan = 'Ejecutando'): array
{
    $accion += ['colaborador_ids' => array_keys($asistencia)];
    [$plan, $accionId] = accionEnPlan($accion, $estadoPlan);
    $lugar = in_array($accion['tipo'] ?? 'Presencial', ['Virtual', 'ELearning'], true) ? 'https://aula.example.com/curso' : 'Sala 1';
    $actividad = actividadDe($accionId, $lugar, $departamentoId);
    foreach ($asistencia as $colaborador => $estado) {
        registroDeAsistencia($actividad['id'], $colaborador, $estado, $estado === 'Presente' ? ($calificaciones[$colaborador] ?? null) : null);
    }

    return ['plan' => $plan, 'accion' => $accionId, 'actividad' => $actividad['id']];
}

function encuestaDe(int $actividadId, int $colaboradorId, int $calificacion = 4, ?string $comentario = null, ?string $fecha = null, int $tenantId = 1): EncuestaSatisfaccionModel
{
    $m = EncuestaSatisfaccionModel::query()->create([
        'tenant_id' => $tenantId, 'actividad_id' => $actividadId, 'colaborador_id' => $colaboradorId, 'calificacion' => $calificacion, 'comentario' => $comentario,
    ]);
    if ($fecha !== null) {
        DB::table('encuestas_satisfaccion')->where('id', $m->id)->update(['created_at' => $fecha.' 10:00:00']);
    }

    return $m;
}

function seguimientoDe(int $actividadId, int $colaboradorId, int $hito, int $aplicacion = 4, int $apoyo = 3, ?string $comentario = null, int $tenantId = 1): SeguimientoImpactoModel
{
    return SeguimientoImpactoModel::query()->create([
        'tenant_id' => $tenantId, 'actividad_id' => $actividadId, 'colaborador_id' => $colaboradorId, 'hito' => $hito,
        'aplicacion' => $aplicacion, 'apoyo' => $apoyo, 'comentario' => $comentario,
    ]);
}

function impactoDeAccion(int $accionId, float $beneficio = 5000.0, int $tenantId = 1, int $usuarioId = 1): ImpactoAccionModel
{
    return ImpactoAccionModel::query()->create([
        'tenant_id' => $tenantId, 'plan_accion_id' => $accionId, 'indicador' => 'Tiempo de cierre', 'unidad' => 'días',
        'valor_antes' => 10, 'valor_despues' => 7, 'beneficio_estimado' => $beneficio, 'justificacion' => 'Menos reprocesos',
        'created_by' => $usuarioId, 'updated_by' => $usuarioId,
    ]);
}

/** Cuerpo válido del PUT de una evaluación de impacto. */
function cuerpoImpacto(array $cambios = []): array
{
    return array_replace([
        'indicador' => 'Tiempo de cierre contable', 'unidad' => 'días', 'valor_antes' => 10, 'valor_despues' => 7,
        'beneficio_estimado' => 5000, 'justificacion' => 'Menos reprocesos tras la capacitación.',
    ], $cambios);
}

/**
 * Registra una DNF con una competencia evaluada para un colaborador en la fecha dada (preparación de escenarios).
 *
 * @return int id de la DNF
 */
function dnfEvaluada(string $fecha, int $colaboradorId, int $competenciaId, string $estado, ?string $nivelActual = null, string $nivelRequerido = 'B', int $tenantId = 1): int
{
    $n = DB::table('dnf')->where('tenant_id', $tenantId)->count() + 1;
    $dnfId = DB::table('dnf')->insertGetId([
        'tenant_id' => $tenantId, 'codigo' => sprintf('DNF-%04d', $n).'-'.uniqid(), 'alcance' => 'Individual', 'competencias_omitidas' => 0,
        'created_by' => 1, 'created_at' => $fecha.' 09:00:00', 'updated_at' => $fecha.' 09:00:00',
    ]);
    $dcId = DB::table('dnf_colaboradores')->insertGetId([
        'dnf_id' => $dnfId, 'colaborador_id' => $colaboradorId, 'puesto_id' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('dnf_detalles')->insert([
        'dnf_colaborador_id' => $dcId, 'competencia_id' => $competenciaId, 'tipo_competencia' => 'Cardinal', 'nivel_requerido' => $nivelRequerido,
        'nivel_actual' => $nivelActual, 'diferencia' => null, 'brecha_porcentaje' => null, 'prioridad' => $estado === 'ConBrecha' ? 'Media' : null, 'estado' => $estado,
    ]);

    return $dnfId;
}

/** Cambia las fechas de una acción ya creada para fijar su estado calculado y su período. */
function fechasDeAccion(int $accionId, string $inicio, string $fin): void
{
    PlanAccionModel::query()->whereKey($accionId)->update(['fecha_inicio' => $inicio, 'fecha_fin' => $fin]);
}
