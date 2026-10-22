<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Database\Factories\MicrocontenidoFactory;
use Illuminate\Support\Facades\DB;

/**
 * Medición de SC-001 (T078): el dashboard del año completo en menos de 5 s con 1 000 actividades, 20 000 asistencias,
 * 5 000 completaciones y 300 DNF. Es lenta, así que solo corre cuando se pide: `REPORTES_RENDIMIENTO=1 vendor/bin/pest <este archivo>`.
 */
it('responde el dashboard anual en menos de 5 segundos con el volumen objetivo', function () {
    $hoy = '2026-10-17';
    $falsos = actividadFalsos(['hoy' => $hoy, 'vigentes' => [10 => 'Ana', 11 => 'Luis']]);
    actuarComoAdminDeTenant();

    $departamentos = [];
    for ($c = 1; $c <= 400; $c++) {
        $departamentos[$c] = ($c % 8) + 1;
    }
    departamentosActuales($departamentos);

    [$planId] = accionEnPlan();
    $plan = PlanModel::query()->findOrFail($planId);
    $ahora = now()->toDateTimeString();

    $acciones = [];
    for ($i = 0; $i < 1000; $i++) {
        $acciones[] = [
            'plan_id' => $plan->id, 'nombre' => "Acción $i", 'tipo' => ['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring'][$i % 6],
            'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-02-20', 'duracion_horas' => 8, 'formador' => 'Formador', 'capacidad_maxima' => 30,
            'costo' => 500, 'cancelada' => false, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($acciones, 100) as $lote) {
        DB::table('plan_acciones')->insert($lote);
    }
    $accionIds = DB::table('plan_acciones')->where('plan_id', $plan->id)->where('nombre', 'like', 'Acción %')->orderBy('id')->pluck('id')->all();

    $participantes = [];
    $actividades = [];
    $asistencias = [];
    foreach ($accionIds as $n => $accionId) {
        $actividades[] = [
            'tenant_id' => 1, 'numero' => 100 + $n, 'codigo' => 'ACT-'.(100 + $n), 'plan_accion_id' => $accionId, 'lugar_enlace' => 'Sala',
            'departamento_id' => ($n % 8) + 1, 'version' => 1, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
        for ($p = 0; $p < 20; $p++) {
            $colaborador = (($n * 7 + $p) % 400) + 1;
            $participantes[] = ['plan_accion_id' => $accionId, 'colaborador_id' => $colaborador, 'created_by' => 1, 'created_at' => $ahora, 'updated_at' => $ahora];
        }
    }
    foreach (array_chunk($participantes, 500) as $lote) {
        DB::table('plan_accion_participantes')->insert($lote);
    }
    foreach (array_chunk($actividades, 100) as $lote) {
        DB::table('actividades')->insert($lote);
    }
    $actividadIds = DB::table('actividades')->where('numero', '>=', 100)->orderBy('id')->pluck('id')->all();
    foreach ($actividadIds as $n => $actividadId) {
        for ($p = 0; $p < 20; $p++) {
            $asistencias[] = [
                'actividad_id' => $actividadId, 'colaborador_id' => (($n * 7 + $p) % 400) + 1, 'asistencia' => 'Presente', 'calificacion' => 3 + ($p % 3),
                'observaciones' => null, 'registrado_by' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ];
        }
    }
    foreach (array_chunk($asistencias, 500) as $lote) {
        DB::table('actividad_asistencias')->insert($lote);
    }

    // Un progreso por colaborador y micro-contenido: 13 contenidos x 400 colaboradores superan los 5 000 completados.
    $micros = array_map(fn () => MicrocontenidoFactory::new()->create()->id, range(1, 13));
    $progresos = [];
    for ($i = 0; $i < 5200; $i++) {
        $progresos[] = [
            'microcontenido_id' => $micros[intdiv($i, 400)], 'colaborador_id' => ($i % 400) + 1, 'tenant_id' => 1, 'estado' => 'Completado',
            'fecha_inicio' => '2026-04-01 10:00:00', 'fecha_completado' => '2026-04-02 10:00:00', 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($progresos, 500) as $lote) {
        DB::table('microcontenido_progresos')->insertOrIgnore($lote);
    }

    for ($d = 0; $d < 300; $d++) {
        dnfEvaluada('2026-0'.(($d % 9) + 1).'-10', ($d % 400) + 1, ($d % 6) + 1, ['ConBrecha', 'Cumple', 'Supera'][$d % 3], 'B');
    }

    $inicio = microtime(true);
    $this->getJson('/api/reportes/dashboard')->assertOk();
    $segundos = microtime(true) - $inicio;

    fwrite(STDERR, sprintf("\nDashboard anual: %.2f s (1 000 actividades, 20 000 asistencias, %d completaciones, 300 DNF)\n", $segundos, MicroProgresoModel::query()->count()));
    expect($segundos)->toBeLessThan(5.0);
})->skip(getenv('REPORTES_RENDIMIENTO') !== '1', 'Medición de rendimiento: ejecutar con REPORTES_RENDIMIENTO=1.');
