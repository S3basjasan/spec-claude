<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

it('cambiar el responsable en un plan Acordado conserva los acuerdos y el estado y deja historial', function () {
    $admin = pdiComoAdmin(['administradores' => [1 => 'Administrador', 2 => 'Otra administradora']]);
    $admin2 = $admin->id;
    pdiFalsos(['administradores' => [(int) $admin->id => 'Administrador', 7 => 'Nuevo responsable']]);
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'responsable' => (int) $admin->id]);

    $r = $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => 7, 'version' => 1])->assertOk();

    expect($r->json('data.estado'))->toBe('Acordado')
        ->and($r->json('data.responsable.id'))->toBe(7)
        ->and($r->json('data.acuerdo_responsable'))->not->toBeNull()
        ->and($r->json('data.aceptacion_colaborador'))->not->toBeNull()
        ->and($r->json('data.version'))->toBe(2);
    $evento = PdiHistorialModel::query()->where('tipo', 'Responsable')->first();
    expect($evento->valor_anterior)->not->toBeNull()->and($evento->valor_nuevo)->not->toBeNull();
});

it('cambiar el responsable en un Borrador cuenta como edición y borra los acuerdos', function () {
    $admin = pdiComoAdmin();
    pdiFalsos(['administradores' => [(int) $admin->id => 'Administrador', 7 => 'Nuevo responsable']]);
    $plan = pdiEnBd(['estado' => EstadoDePdi::Borrador, 'acuerdo' => true, 'responsable' => (int) $admin->id]);

    $r = $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => 7, 'version' => 1])->assertOk();

    expect($r->json('data.estado'))->toBe('Borrador')->and($r->json('data.acuerdo_responsable'))->toBeNull();
});

it('rechaza un destino que no es administrador activo y un plan Cerrado', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'responsable' => (int) $admin->id]);

    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => 999, 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors('responsable_id');

    PdiModel::query()->whereKey($plan->id)->update(['estado' => EstadoDePdi::Cerrado->value]);
    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => (int) $admin->id, 'version' => 1])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
});

it('señala al responsable que ya no es administrador y permite reasignarlo aunque el plan esté Acordado', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['estado' => EstadoDePdi::Acordado, 'responsable' => 55]);

    $this->getJson("/api/pdi/{$plan->id}")->assertOk()->assertJsonPath('data.responsable.sin_permiso', true);

    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => (int) $admin->id, 'version' => 1])->assertOk()
        ->assertJsonPath('data.responsable.sin_permiso', false)->assertJsonPath('data.estado', 'Acordado');
});

it('exige la versión y rechaza una desfasada con 409; el colaborador no puede cambiar el responsable', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['responsable' => (int) $admin->id]);

    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => (int) $admin->id])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => (int) $admin->id, 'version' => 5])->assertStatus(409);

    actuarComoUsuarioDeTenant();
    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => (int) $admin->id, 'version' => 1])->assertForbidden();
});
