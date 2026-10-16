<?php

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\UseCases\CrearPlan;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Testing\TestResponse;
use Tests\Support\Desarrollo\AprobadoresFalso;
use Tests\Support\Desarrollo\AsistenciaFalsa;
use Tests\Support\Desarrollo\ColaboradoresVigentesFalso;
use Tests\Support\Desarrollo\CompetenciasFalsas;
use Tests\Support\Desarrollo\NombresDeReferenciaFalsos;
use Tests\Support\Desarrollo\RelojFijo;

/**
 * Helpers de la feature 013 (Plan de formación anual). Datos sintéticos.
 */

/**
 * Enlaza en el contenedor los puertos del plan con dobles y los devuelve.
 *
 * @param  array<string, mixed>  $o  vigentes, administradores, hoy, competencias, inactivas, presentes
 * @return array{vigentes: ColaboradoresVigentesFalso, aprobadores: AprobadoresFalso, reloj: RelojFijo, asistencia: AsistenciaFalsa}
 */
function planFalsos(array $o = []): array
{
    $vigentes = new ColaboradoresVigentesFalso($o['vigentes'] ?? [10 => 'Ana Pérez', 11 => 'Luis Mora', 12 => 'Eva Ríos', 13 => 'Pedro Sol', 14 => 'Rita Paz']);
    $aprobadores = new AprobadoresFalso($o['administradores'] ?? []);
    $reloj = new RelojFijo($o['hoy'] ?? '2026-01-15');
    $asistencia = new AsistenciaFalsa($o['presentes'] ?? []);
    $inactivas = $o['inactivas'] ?? [9];

    app()->instance(ColaboradoresVigentesInterface::class, $vigentes);
    app()->instance(AprobadoresInterface::class, $aprobadores);
    app()->instance(RelojInterface::class, $reloj);
    app()->instance(AsistenciaInterface::class, $asistencia);
    app()->instance(CompetenciasInterface::class, new CompetenciasFalsas($o['competencias'] ?? [1, 2, 3], $inactivas));
    app()->instance(NombresDeReferenciaInterface::class, new NombresDeReferenciaFalsos($inactivas));

    return ['vigentes' => $vigentes, 'aprobadores' => $aprobadores, 'reloj' => $reloj, 'asistencia' => $asistencia];
}

/** Acción completa y válida para el cuerpo de un plan. */
function cuerpoAccion(array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Análisis con SQL',
        'tipo' => 'Virtual',
        'fecha_inicio' => '2026-03-02',
        'fecha_fin' => '2026-03-13',
        'duracion_horas' => 16,
        'formador' => 'Ing. Vera',
        'capacidad_maxima' => 20,
        'costo' => 800,
        'competencia_ids' => [1],
        'colaborador_ids' => [10, 11],
    ], $cambios);
}

/** Cuerpo válido para crear o actualizar un plan (una acción completa por defecto). */
function cuerpoPlan(array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Plan técnico 2026',
        'anio' => 2026,
        'descripcion' => null,
        'aprobador_id' => null,
        'acciones' => [cuerpoAccion()],
    ], $cambios);
}

/** Crea un plan por el caso de uso (los puertos deben estar enlazados con `planFalsos`) y devuelve el arreglo del contrato. */
function planGuardado(array $cambios = [], int $tenantId = 1, int $actorId = 1): array
{
    return app(CrearPlan::class)->ejecutar($tenantId, $actorId, cuerpoPlan($cambios));
}

/** Fuerza el estado de un plan sin pasar por las reglas (preparación de escenarios). */
function forzarEstadoPlan(int $planId, string $estado): void
{
    PlanModel::query()->whereKey($planId)->update(['estado' => $estado]);
}

/** Fuerza fechas de una acción (para fijar su estado calculado). */
function forzarFechasAccion(int $accionId, string $inicio, string $fin): void
{
    PlanAccionModel::query()->whereKey($accionId)->update(['fecha_inicio' => $inicio, 'fecha_fin' => $fin]);
}

function crearPlanApi(mixed $prueba, array $cuerpo): TestResponse
{
    return $prueba->postJson('/api/planes', $cuerpo);
}

/**
 * Crea la ficha de un colaborador y su cuenta de usuario, y actúa como ese usuario.
 *
 * @return array{0: UserModel, 1: int} usuario e id de la ficha
 */
function colaboradorConCuentaDePlan(int $tenantId = 1): array
{
    $ficha = fichaDe([], $tenantId);
    $usuario = actuarComoUsuarioDeTenant($tenantId);
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => $tenantId, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id, 'puede_consultar_ficha' => true,
    ]);

    return [$usuario, (int) $ficha->id];
}
