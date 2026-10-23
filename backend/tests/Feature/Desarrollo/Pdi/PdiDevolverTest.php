<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

it('el colaborador devuelve con comentario: sigue en Borrador, sin acuerdos, con el comentario y deja de verlo', function () {
    [$id, , $fichaId] = pdiParaAcordar();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();
    actuarComoColaboradorDelPlan($fichaId);

    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 2])->assertUnprocessable()->assertJsonValidationErrors('comentario');
    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 2, 'comentario' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('comentario');

    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 2, 'comentario' => 'Necesito más tiempo para el curso.'])
        ->assertOk()->assertJsonPath('data.estado', 'Borrador')->assertJsonPath('data.version', 3);

    $this->getJson("/api/pdi/{$id}")->assertNotFound();
    $plan = PdiModel::query()->find($id);
    expect($plan->acuerdo_responsable_at)->toBeNull()
        ->and($plan->aceptacion_colaborador_at)->toBeNull()
        ->and($plan->comentario_colaborador)->toBe('Necesito más tiempo para el curso.')
        ->and(PdiHistorialModel::query()->where('tipo', 'Devolucion')->value('motivo'))->toBe('Necesito más tiempo para el curso.');

    actuarComoAdminDeTenant();
    $this->getJson("/api/pdi/{$id}")->assertOk()->assertJsonPath('data.comentario_colaborador', 'Necesito más tiempo para el curso.');
});

it('el colaborador no puede devolver un plan sin acuerdo ni uno ya Acordado', function () {
    [$id, , $fichaId] = pdiParaAcordar();
    actuarComoColaboradorDelPlan($fichaId);
    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 1, 'comentario' => 'x'])->assertNotFound();

    actuarComoAdminDeTenant();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();
    actuarComoColaboradorDelPlan($fichaId);
    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 2])->assertOk();
    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 3, 'comentario' => 'x'])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
});

it('el administrador devuelve un Acordado a Borrador con motivo y se borran los acuerdos', function () {
    [$id, , $fichaId] = pdiParaAcordar();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();
    actuarComoColaboradorDelPlan($fichaId);
    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 2])->assertOk();
    actuarComoAdminDeTenant();

    $this->postJson("/api/pdi/{$id}/devolver", ['version' => 3])->assertUnprocessable()->assertJsonValidationErrors('motivo');

    $r = $this->postJson("/api/pdi/{$id}/devolver", ['version' => 3, 'motivo' => 'Cambió el alcance del proyecto.'])->assertOk();

    expect($r->json('data.estado'))->toBe('Borrador')
        ->and($r->json('data.acuerdo_responsable'))->toBeNull()
        ->and($r->json('data.aceptacion_colaborador'))->toBeNull()
        ->and($r->json('data.version'))->toBe(4)
        ->and(PdiHistorialModel::query()->where('tipo', 'Devolucion')->value('motivo'))->toBe('Cambió el alcance del proyecto.')
        ->and(PdiHistorialModel::query()->where('tipo', 'Estado')->where('valor_nuevo', 'Borrador')->exists())->toBeTrue();
    actuarComoColaboradorDelPlan($fichaId);
    $this->getJson("/api/pdi/{$id}")->assertNotFound();
});

it('solo se devuelve a Borrador un plan Acordado y solo con permiso de gestión', function () {
    $admin = pdiComoAdmin();
    $id = $this->postJson('/api/pdi', pdiCuerpo($admin->id))->json('data.id');

    $this->postJson("/api/pdi/{$id}/devolver", ['version' => 1, 'motivo' => 'x'])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');

    PdiModel::query()->whereKey($id)->update(['estado' => EstadoDePdi::Cerrado->value]);
    $this->postJson("/api/pdi/{$id}/devolver", ['version' => 1, 'motivo' => 'x'])->assertUnprocessable();

    actuarComoUsuarioDeTenant();
    $this->postJson("/api/pdi/{$id}/devolver", ['version' => 1, 'motivo' => 'x'])->assertForbidden();
});
