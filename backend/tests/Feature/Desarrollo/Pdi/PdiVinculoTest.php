<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;
use Illuminate\Support\Facades\DB;

/**
 * Administrador activo, un plan de formación Ejecutando con una acción en la que participan los colaboradores 10 y 11 y
 * «hoy» el 2026-08-01 (dentro de las fechas de las acciones de los PDI de prueba).
 *
 * @return array{0: int, 1: int, 2: int} id del administrador, id del plan de formación e id de su acción
 */
function pdiEscenarioDeVinculo(string $estadoPlan = 'Ejecutando', string $hoy = '2026-08-01'): array
{
    $admin = actuarComoAdminDeTenant();
    $administradores = [(int) $admin->id => 'Administrador'];
    actividadFalsos(['administradores' => $administradores]);
    [$planId, $accionId] = accionEnPlan([], $estadoPlan);
    pdiFalsos(['administradores' => $administradores, 'hoy' => $hoy]);

    return [(int) $admin->id, $planId, $accionId];
}

it('el selector lista solo acciones de planes Aprobado o Ejecutando donde el colaborador participa, sin canceladas', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    [, $aprobada] = accionEnPlan([], 'Aprobado');
    [, $borrador] = accionEnPlan([], 'Borrador');
    [, $cerrada] = accionEnPlan([], 'Cerrado');
    [, $cancelada] = accionEnPlan([], 'Ejecutando');
    [, $deOtros] = accionEnPlan(['colaborador_ids' => [12]], 'Ejecutando');
    DB::table('plan_acciones')->where('id', $cancelada)->update(['cancelada' => true]);

    $ids = collect($this->getJson('/api/pdi/acciones-vinculables?colaborador_id=10')->assertOk()->json('data'))->pluck('plan_accion_id')->all();

    expect($ids)->toContain($accionId, $aprobada)
        ->not->toContain($borrador, $cerrada, $cancelada, $deOtros);
    expect($this->getJson('/api/pdi/acciones-vinculables?colaborador_id=10&q=zzz')->json('data'))->toBe([])
        ->and($this->getJson('/api/pdi/acciones-vinculables')->status())->toBe(422);
    expect(array_column(DesarrolloFacade::getAccionesVinculablesDeColaborador(1, 10), 'plan_accion_id'))->toBe($ids);
});

it('vincular una acción Curso deriva su estado y es de solo lectura', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado]);
    $pdiAccionId = $plan->compromisos[0]->acciones[0]->id;

    $r = $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => $accionId])->assertOk();

    expect($r->json('data.compromisos.0.acciones.0.vinculo.plan_accion_id'))->toBe($accionId)
        ->and($r->json('data.compromisos.0.acciones.0.vinculo.plan_codigo'))->not->toBeNull()
        ->and($r->json('data.compromisos.0.acciones.0.estado_derivado'))->toBeTrue()
        ->and($r->json('data.compromisos.0.acciones.0.estado'))->toBe('EnCurso')
        ->and($r->json('data.estado'))->toBe('Acordado')
        ->and($r->json('data.acuerdo_responsable'))->not->toBeNull()
        ->and(PdiHistorialModel::query()->where('tipo', 'Vinculo')->count())->toBe(1);

    $this->patchJson("/api/pdi-acciones/{$pdiAccionId}/avance", ['estado' => 'Cumplida'])->assertUnprocessable();
});

it('con la actividad Finalizada y la asistencia Presente la acción vinculada es Cumplida; si el plan la cancela, Cancelada', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'vinculo' => null]);
    $pdiAccionId = $plan->compromisos[0]->acciones[0]->id;
    $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => $accionId])->assertOk();

    $actividad = actividadDe($accionId);
    registroDeAsistencia($actividad['id'], 10, 'Presente');
    $this->getJson("/api/pdi/{$plan->id}")->assertJsonPath('data.compromisos.0.acciones.0.estado', 'Cumplida')->assertJsonPath('data.avance', 100);

    DB::table('plan_acciones')->where('id', $accionId)->update(['cancelada' => true]);
    $this->getJson("/api/pdi/{$plan->id}")->assertJsonPath('data.compromisos.0.acciones.0.estado', 'Cancelada')->assertJsonPath('data.avance', null);
});

it('solo se vinculan acciones de tipo Curso, Mentoring o Codesarrollo y que estén en la lista de vinculables', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    [, $deOtros] = accionEnPlan(['colaborador_ids' => [12]], 'Ejecutando');
    foreach ([TipoDeAccionPdi::Coaching, TipoDeAccionPdi::Autodesarrollo, TipoDeAccionPdi::NuevaResponsabilidad, TipoDeAccionPdi::Otra] as $i => $tipo) {
        $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'tipoAccion' => $tipo, 'colaborador' => 10 + $i, 'tipo' => TipoDePdi::Mejora]);
        $this->putJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/vinculo', ['plan_accion_id' => $accionId])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_accion_id');
    }

    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'colaborador' => 10, 'tipo' => TipoDePdi::Carrera, 'tipoAccion' => TipoDeAccionPdi::Mentoring]);
    $this->putJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/vinculo', ['plan_accion_id' => $deOtros])->assertUnprocessable();
    $this->putJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/vinculo', ['plan_accion_id' => 999999])->assertUnprocessable();
    $this->putJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/vinculo', [])->assertUnprocessable()->assertJsonValidationErrors('plan_accion_id');
});

it('vincular en un Borrador cuenta como edición y borra los acuerdos; en un Cerrado no se puede', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Borrador, 'acuerdo' => true]);
    $pdiAccionId = $plan->compromisos[0]->acciones[0]->id;

    $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => $accionId])->assertUnprocessable()->assertJsonValidationErrors('version');
    $r = $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => $accionId, 'version' => 1])->assertOk();
    expect($r->json('data.acuerdo_responsable'))->toBeNull()->and($r->json('data.version'))->toBe(2);
    $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => null, 'version' => 1])->assertStatus(409);

    PdiModel::query()->whereKey($plan->id)->update(['estado' => EstadoDePdi::Cerrado->value]);
    $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => null, 'version' => 2])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
});

it('desvincular deja como estado manual el último que mostraba y permite registrar avances', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado]);
    $pdiAccionId = $plan->compromisos[0]->acciones[0]->id;
    $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => $accionId])->assertOk();

    $r = $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => null])->assertOk();

    expect($r->json('data.compromisos.0.acciones.0.vinculo'))->toBeNull()
        ->and($r->json('data.compromisos.0.acciones.0.estado'))->toBe('EnCurso')
        ->and($r->json('data.compromisos.0.acciones.0.estado_derivado'))->toBeFalse();
    $this->patchJson("/api/pdi-acciones/{$pdiAccionId}/avance", ['estado' => 'Cumplida'])->assertOk();
    expect(PdiHistorialModel::query()->where('tipo', 'Vinculo')->count())->toBe(2);
});

it('si el colaborador deja de ser participante el vínculo requiere revisión y el Plan de Formación no se modifica', function () {
    [, , $accionId] = pdiEscenarioDeVinculo();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado]);
    $pdiAccionId = $plan->compromisos[0]->acciones[0]->id;
    $this->putJson("/api/pdi-acciones/{$pdiAccionId}/vinculo", ['plan_accion_id' => $accionId])->assertOk();
    $antes = [DB::table('plan_acciones')->count(), DB::table('plan_accion_participantes')->count(), DB::table('planes')->value('updated_at')];

    DB::table('plan_accion_participantes')->where('plan_accion_id', $accionId)->where('colaborador_id', 10)->delete();

    $this->getJson("/api/pdi/{$plan->id}")->assertOk()->assertJsonPath('data.compromisos.0.acciones.0.vinculo.requiere_revision', true);
    expect([DB::table('plan_acciones')->count(), DB::table('plan_accion_participantes')->count() + 1, DB::table('planes')->value('updated_at')])->toBe($antes);
});

it('el colaborador no puede vincular ni listar las acciones vinculables', function () {
    pdiFalsos();
    actuarComoUsuarioDeTenant();

    $this->putJson('/api/pdi-acciones/1/vinculo', ['plan_accion_id' => null])->assertForbidden();
    $this->getJson('/api/pdi/acciones-vinculables?colaborador_id=10')->assertForbidden();
});

it('crear y editar un plan acepta un plan_accion_id vinculable', function () {
    [$adminId, , $accionId] = pdiEscenarioDeVinculo();
    $cuerpo = pdiCuerpo($adminId);
    $cuerpo['compromisos'][0]['acciones'][0]['plan_accion_id'] = $accionId;

    $r = $this->postJson('/api/pdi', $cuerpo)->assertCreated();

    expect($r->json('data.compromisos.0.acciones.0.vinculo.plan_accion_id'))->toBe($accionId);
    $this->putJson('/api/pdi/'.$r->json('data.id'), $cuerpo + ['version' => 1])->assertOk()->assertJsonPath('data.compromisos.0.acciones.0.vinculo.plan_accion_id', $accionId);
});
