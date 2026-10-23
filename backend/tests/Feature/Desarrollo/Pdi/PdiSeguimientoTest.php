<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHitoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

it('registra el avance de una acción sin vínculo en un plan Acordado con historial', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado]);
    $accionId = $plan->compromisos[0]->acciones[0]->id;

    $r = $this->patchJson("/api/pdi-acciones/{$accionId}/avance", ['estado' => 'Cumplida'])->assertOk();

    expect($r->json('data.compromisos.0.acciones.0.estado'))->toBe('Cumplida')
        ->and($r->json('data.avance'))->toBe(100);
    $evento = PdiHistorialModel::query()->where('tipo', 'Avance')->first();
    expect($evento->referencia_id)->toBe($accionId)->and($evento->valor_anterior)->toBe('Pendiente')->and($evento->valor_nuevo)->toBe('Cumplida');
});

it('no registra avances en acciones vinculadas ni en planes que no están Acordados', function () {
    pdiComoAdmin();
    $vinculada = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'vinculo' => 55]);
    $this->patchJson('/api/pdi-acciones/'.$vinculada->compromisos[0]->acciones[0]->id.'/avance', ['estado' => 'Cumplida'])
        ->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');

    foreach ([EstadoDePdi::Borrador, EstadoDePdi::Cerrado] as $estado) {
        $plan = pdiEnBd(['estado' => $estado, 'colaborador' => 11, 'tipo' => $estado === EstadoDePdi::Borrador ? TipoDePdi::Mejora : TipoDePdi::Carrera]);
        $this->patchJson('/api/pdi-acciones/'.$plan->compromisos[0]->acciones[0]->id.'/avance', ['estado' => 'Cumplida'])->assertUnprocessable();
    }
    $this->patchJson('/api/pdi-acciones/999/avance', ['estado' => 'Cumplida'])->assertNotFound();
    $this->patchJson('/api/pdi-acciones/1/avance', ['estado' => 'Otro'])->assertUnprocessable()->assertJsonValidationErrors('estado');
});

it('marca un hito como Realizado con observaciones y lo registra en el historial', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado]);
    $hitoId = $plan->hitos[0]->id;

    $r = $this->patchJson("/api/pdi-hitos/{$hitoId}", ['estado' => 'Realizado', 'observaciones' => 'Revisión hecha con la jefatura.'])->assertOk();

    expect($r->json('data.hitos.0.estado'))->toBe('Realizado')
        ->and($r->json('data.hitos.0.observaciones'))->toBe('Revisión hecha con la jefatura.')
        ->and($r->json('data.hitos.0.vencido'))->toBeFalse();
    expect(PdiHistorialModel::query()->where('tipo', 'Hito')->where('referencia_id', $hitoId)->exists())->toBeTrue()
        ->and(PdiHitoModel::query()->find($hitoId)->registrado_by)->not->toBeNull();

    PdiModel::query()->whereKey($plan->id)->update(['estado' => EstadoDePdi::Borrador->value]);
    $this->patchJson("/api/pdi-hitos/{$hitoId}", ['estado' => 'Pendiente'])->assertUnprocessable();
});

it('la estructura de un plan Acordado no se edita', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado]);

    $this->putJson("/api/pdi/{$plan->id}", pdiCuerpo($admin->id) + ['version' => 1])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
});

it('calcula el avance como Cumplidas sobre no Canceladas y marca los hitos vencidos', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cancelada', 'Cumplida', 'Cumplida', 'Pendiente'], 'hitos' => [['2026-06-01'], ['2026-09-01'], ['2026-05-01', 'Realizado']]]);

    $r = $this->getJson("/api/pdi/{$plan->id}")->assertOk();

    expect($r->json('data.avance'))->toBe(67)
        ->and($r->json('data.hitos_vencidos'))->toBe(1)
        ->and(collect($r->json('data.hitos'))->pluck('vencido')->all())->toBe([false, true, false]);
});

it('un plan con todas las acciones Canceladas no tiene avance calculable', function () {
    pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'acciones' => ['Cancelada', 'Cancelada']]);

    $this->getJson("/api/pdi/{$plan->id}")->assertOk()->assertJsonPath('data.avance', null);
    expect(PdiAccionModel::query()->count())->toBe(2);
});

it('el colaborador no puede registrar avances', function () {
    pdiFalsos();
    actuarComoUsuarioDeTenant();

    $this->patchJson('/api/pdi-acciones/1/avance', ['estado' => 'Cumplida'])->assertForbidden();
    $this->patchJson('/api/pdi-hitos/1', ['estado' => 'Realizado'])->assertForbidden();
});
