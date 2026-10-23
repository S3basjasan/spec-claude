<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiCompromisoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use Illuminate\Support\Facades\DB;

it('no cierra con acciones Pendientes o En curso y dice cuántas faltan', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cumplida', 'Pendiente', 'EnCurso']]);
    $compromisoId = $plan->compromisos[0]->id;

    $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $compromisoId, 'resultado' => 'Cerrada']]])
        ->assertUnprocessable()->assertJsonPath('code', 'acciones_abiertas')->assertJsonPath('acciones_abiertas', 2);
});

it('exige el resultado de cada compromiso', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cumplida']]);

    $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('resultados');
    $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => 9999, 'resultado' => 'Cerrada']]])->assertUnprocessable();
    $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $plan->compromisos[0]->id, 'resultado' => 'Otro']]])
        ->assertUnprocessable()->assertJsonValidationErrors('resultados.0.resultado');
});

it('cierra guardando los resultados, el del plan, la conclusión, la auditoría y deja el plan en solo lectura', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cumplida', 'Cancelada']]);
    $compromisoId = $plan->compromisos[0]->id;

    $r = $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'conclusion' => 'Se alcanzó el nivel B.', 'resultados' => [['compromiso_id' => $compromisoId, 'resultado' => 'Cerrada']]])->assertOk();

    expect($r->json('data.resultado'))->toBe('BrechaCerrada')
        ->and($r->json('data.resultado_etiqueta'))->toBe('Brecha cerrada')
        ->and($r->json('data.plan.estado'))->toBe('Cerrado')
        ->and($r->json('data.plan.conclusion'))->toBe('Se alcanzó el nivel B.')
        ->and($r->json('data.plan.compromisos.0.resultado'))->toBe('Cerrada')
        ->and($r->json('data.plan.cerrado_en'))->not->toBeNull()
        ->and($r->json('data.ofrecer_adecuacion'))->toBeNull()
        ->and(PdiHistorialModel::query()->where('tipo', 'Cierre')->value('valor_nuevo'))->toBe('BrechaCerrada')
        ->and(PdiHistorialModel::query()->where('tipo', 'Estado')->where('valor_nuevo', 'Cerrado')->exists())->toBeTrue();

    $cuerpo = ['version' => 2];
    $this->putJson("/api/pdi/{$plan->id}", pdiCuerpo($admin->id) + $cuerpo)->assertUnprocessable();
    $this->patchJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/avance', ['estado' => 'Pendiente'])->assertUnprocessable();
    $this->patchJson('/api/pdi-hitos/'.$plan->hitos[0]->id, ['estado' => 'Realizado'])->assertUnprocessable();
    $this->putJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/vinculo', ['plan_accion_id' => null])->assertUnprocessable();
    $this->postJson("/api/pdi/{$plan->id}/devolver", $cuerpo + ['motivo' => 'x'])->assertUnprocessable();
    $this->patchJson("/api/pdi/{$plan->id}/responsable", $cuerpo + ['responsable_id' => (int) $admin->id])->assertUnprocessable();
    $this->postJson("/api/pdi/{$plan->id}/cerrar", $cuerpo + ['resultados' => [['compromiso_id' => $compromisoId, 'resultado' => 'Cerrada']]])->assertUnprocessable();
    $this->deleteJson("/api/pdi/{$plan->id}")->assertStatus(405);
});

it('cierra un plan con todas las acciones Canceladas y muestra el avance como no calculable', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cancelada', 'Cancelada']]);

    $r = $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $plan->compromisos[0]->id, 'resultado' => 'NoCerrada']]])->assertOk();

    expect($r->json('data.resultado'))->toBe('NoCerrada')->and($r->json('data.plan.avance'))->toBeNull();
});

it('Carrera y Talento ofrecen generar una nueva adecuación; Mejora no', function () {
    pdiComoAdmin();
    foreach ([[TipoDePdi::Carrera, 3, 11], [TipoDePdi::Talento, 2, 12]] as [$tipo, $puesto, $colaborador]) {
        $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'tipo' => $tipo, 'puesto' => $puesto, 'colaborador' => $colaborador, 'acciones' => ['Cumplida']]);
        $r = $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $plan->compromisos[0]->id, 'resultado' => 'Parcial']]])->assertOk();

        expect($r->json('data.resultado'))->toBe('NoCerrada')
            ->and($r->json('data.ofrecer_adecuacion'))->toBe(['colaborador_id' => $colaborador, 'puesto_objetivo_id' => $puesto]);
    }
});

it('solo se cierra un Acordado y solo con permiso de cierre; la versión es obligatoria', function () {
    pdiComoAdmin();
    $borrador = pdiEnBd();

    $this->postJson("/api/pdi/{$borrador->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $borrador->compromisos[0]->id, 'resultado' => 'Cerrada']]])
        ->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    $this->postJson("/api/pdi/{$borrador->id}/cerrar", [])->assertUnprocessable()->assertJsonValidationErrors(['version', 'resultados']);
    $this->postJson("/api/pdi/{$borrador->id}/cerrar", ['version' => 4, 'resultados' => [['compromiso_id' => 1, 'resultado' => 'Cerrada']]])->assertStatus(422);

    actuarComoUsuarioDeTenant();
    $this->postJson("/api/pdi/{$borrador->id}/cerrar", ['version' => 1, 'resultados' => []])->assertForbidden();
});

it('un plan Cerrado permite crear uno nuevo del mismo tipo para el colaborador', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cumplida']]);
    $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $plan->compromisos[0]->id, 'resultado' => 'Cerrada']]])->assertOk();

    $this->postJson('/api/pdi', pdiCuerpo($admin->id))->assertCreated();
});

it('al cerrar congela el estado de las acciones vinculadas y los cambios posteriores del Plan de Formación no lo alteran', function () {
    $admin = actuarComoAdminDeTenant();
    $administradores = [(int) $admin->id => 'Administrador'];
    actividadFalsos(['administradores' => $administradores]);
    [, $accionDelPlan] = accionEnPlan([], 'Ejecutando');
    pdiFalsos(['administradores' => $administradores, 'hoy' => '2026-08-01']);
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Pendiente'], 'vinculo' => $accionDelPlan]);
    $actividad = actividadDe($accionDelPlan);
    registroDeAsistencia($actividad['id'], 10, 'Presente');

    $this->postJson("/api/pdi/{$plan->id}/cerrar", ['version' => 1, 'resultados' => [['compromiso_id' => $plan->compromisos[0]->id, 'resultado' => 'Cerrada']]])->assertOk();

    $guardada = PdiAccionModel::query()->find($plan->compromisos[0]->acciones[0]->id);
    expect($guardada->estado)->toBe('Cumplida')->and($guardada->estado_congelado)->toBeTrue()->and($guardada->plan_accion_id)->toBe($accionDelPlan);

    DB::table('plan_acciones')->where('id', $accionDelPlan)->update(['cancelada' => true]);
    DB::table('actividad_asistencias')->update(['asistencia' => 'Ausente']);

    $this->getJson("/api/pdi/{$plan->id}")->assertOk()
        ->assertJsonPath('data.compromisos.0.acciones.0.estado', 'Cumplida')
        ->assertJsonPath('data.compromisos.0.acciones.0.estado_derivado', false)
        ->assertJsonPath('data.compromisos.0.acciones.0.vinculo.plan_accion_id', $accionDelPlan);
    expect(PdiCompromisoModel::query()->count())->toBe(1);
});
