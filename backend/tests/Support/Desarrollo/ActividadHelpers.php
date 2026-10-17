<?php

use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\UseCases\CrearActividad;
use App\Modules\Desarrollo\Infrastructure\Adapters\AsistenciaDesdeActividades;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use Tests\Support\Desarrollo\DepartamentosFalsos;

/**
 * Helpers de la feature 017 (Actividades formativas). Datos sintéticos; se apoyan en los de la feature 013.
 */

/**
 * Enlaza los puertos del plan y de actividades con dobles. El «hoy» por defecto (2026-03-05) cae dentro de la acción
 * por defecto (2026-03-02 a 2026-03-13), así que la actividad queda En curso.
 *
 * @param  array<string, mixed>  $o  hoy, vigentes, departamentos, inactivos y los de `planFalsos`
 * @return array<string, mixed>
 */
function actividadFalsos(array $o = []): array
{
    $falsos = planFalsos($o + ['hoy' => '2026-03-05']);
    $departamentos = new DepartamentosFalsos($o['departamentos'] ?? [1 => 'Tecnología', 2 => 'Talento Humano'], $o['inactivos'] ?? [9 => 'Unidad antigua']);
    app()->instance(DepartamentosInterface::class, $departamentos);
    // La asistencia del plan sale de las actividades reales, no del doble de la feature 013.
    app()->bind(AsistenciaInterface::class, AsistenciaDesdeActividades::class);

    return $falsos + ['departamentos' => $departamentos];
}

/**
 * Crea un plan con una acción y fuerza su estado.
 *
 * @param  array<string, mixed>  $accion  cambios sobre la acción por defecto (Presencial, 2026-03-02 a 2026-03-13, inscritos 10 y 11)
 * @param  array<string, mixed>  $plan  cambios sobre el plan
 * @return array{0: int, 1: int} id del plan e id de la acción
 */
function accionEnPlan(array $accion = [], string $estadoPlan = 'Ejecutando', array $plan = [], int $actorId = 1): array
{
    $guardado = planGuardado(['nombre' => 'Plan '.uniqid(), 'acciones' => [cuerpoAccion($accion + ['tipo' => 'Presencial'])]] + $plan, actorId: $actorId);
    forzarEstadoPlan($guardado['id'], $estadoPlan);

    return [$guardado['id'], $guardado['acciones'][0]['id']];
}

/**
 * Crea una actividad por el caso de uso.
 *
 * @return array<string, mixed> detalle de la actividad
 */
function actividadDe(int $accionId, string $lugar = 'Sala 1', ?int $departamentoId = null, int $tenantId = 1, int $actorId = 1): array
{
    return app(CrearActividad::class)->ejecutar($tenantId, $actorId, $accionId, $lugar, $departamentoId);
}

/** Guarda un registro de asistencia directamente (preparación de escenarios). */
function registroDeAsistencia(int $actividadId, int $colaboradorId, string $asistencia, ?int $calificacion = null, ?string $observaciones = null): void
{
    ActividadAsistenciaModel::query()->create([
        'actividad_id' => $actividadId, 'colaborador_id' => $colaboradorId, 'asistencia' => $asistencia,
        'calificacion' => $calificacion, 'observaciones' => $observaciones, 'registrado_by' => 1,
    ]);
}

/**
 * Cuerpo del PUT de asistencia.
 *
 * @param  list<array<string, mixed>>  $registros
 * @return array<string, mixed>
 */
function cuerpoAsistencia(int $version, array $registros): array
{
    return ['version' => $version, 'registros' => $registros];
}
