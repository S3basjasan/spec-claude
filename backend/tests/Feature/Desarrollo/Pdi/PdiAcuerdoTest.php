<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

/**
 * Crea un plan por la API como administrador y devuelve [id, adminId]; el colaborador del plan es la ficha del usuario
 * colaborador que se crea después (el plan se reasigna a su ficha).
 *
 * @return array{0: int, 1: int, 2: int} id del plan, id del administrador e id de la ficha del colaborador
 */
function pdiParaAcordar(array $cuerpo = []): array
{
    $admin = pdiComoAdmin();
    $id = test()->postJson('/api/pdi', pdiCuerpo($admin->id, $cuerpo))->assertCreated()->json('data.id');
    [, $fichaId] = colaboradorConCuentaDePlan();
    PdiModel::query()->whereKey($id)->update(['colaborador_id' => $fichaId]);
    actuarComoAdminDeTenant();

    return [$id, (int) $admin->id, $fichaId];
}

it('no confirma el acuerdo de un plan incompleto y lista lo que falta', function () {
    $admin = pdiComoAdmin();
    $sin = $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => []]))->assertCreated()->json('data.id');
    $sinAcciones = $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['colaborador_id' => 11, 'compromisos' => [array_replace(pdiCuerpo($admin->id)['compromisos'][0], ['acciones' => []])]]))->assertCreated()->json('data.id');

    $this->postJson("/api/pdi/{$sin}/acuerdo", ['version' => 1])->assertUnprocessable()->assertJsonPath('code', 'plan_incompleto')->assertJsonPath('completitud', ['sin_compromisos']);
    $this->postJson("/api/pdi/{$sinAcciones}/acuerdo", ['version' => 1])->assertUnprocessable()->assertJsonPath('completitud', ['compromiso_sin_acciones']);
});

it('el acuerdo del responsable registra usuario y fecha, deja el plan en Borrador y lo hace visible al colaborador', function () {
    [$id] = pdiParaAcordar();

    $r = $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();

    expect($r->json('data.estado'))->toBe('Borrador')
        ->and($r->json('data.version'))->toBe(2)
        ->and($r->json('data.acuerdo_responsable.fecha'))->not->toBeNull()
        ->and($r->json('data.acuerdo_responsable.usuario'))->not->toBeNull()
        ->and($r->json('data.acciones_permitidas.acordar'))->toBeFalse()
        ->and(PdiModel::query()->find($id)->acuerdo_responsable_by)->not->toBeNull()
        ->and(PdiHistorialModel::query()->where('tipo', 'Acuerdo')->count())->toBe(1);

    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 2])->assertUnprocessable()->assertJsonPath('code', 'acuerdo_vigente');
});

it('un Borrador sin acuerdo del responsable no existe para el colaborador, ni para aceptarlo', function () {
    [$id] = pdiParaAcordar();

    actuarComoUsuarioDeTenant();
    $this->getJson("/api/pdi/{$id}")->assertNotFound();
});

it('el colaborador acepta con el acuerdo vigente y el plan pasa a Acordado', function () {
    [$id, , $fichaId] = pdiParaAcordar();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();
    actuarComoColaboradorDelPlan($fichaId);

    $detalle = $this->getJson("/api/pdi/{$id}")->assertOk();
    expect($detalle->json('data.acciones_permitidas.aceptar'))->toBeTrue()
        ->and($detalle->json('data.acciones_permitidas.editar'))->toBeFalse();

    $r = $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 2])->assertOk();

    expect($r->json('data.estado'))->toBe('Acordado')
        ->and($r->json('data.aceptacion_colaborador'))->not->toBeNull()
        ->and($r->json('data.version'))->toBe(3)
        ->and(PdiHistorialModel::query()->where('tipo', 'Aceptacion')->count())->toBe(1)
        ->and(PdiHistorialModel::query()->where('tipo', 'Estado')->where('valor_nuevo', 'Acordado')->count())->toBe(1);
    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 3])->assertUnprocessable();
});

it('aceptar antes del acuerdo del responsable no es posible', function () {
    [$id, , $fichaId] = pdiParaAcordar();
    actuarComoColaboradorDelPlan($fichaId);

    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 1])->assertNotFound();
    expect(PdiModel::query()->find($id)->estado)->toBe(EstadoDePdi::Borrador->value);
});

it('un colaborador no puede aceptar el plan de otra persona', function () {
    [$id] = pdiParaAcordar();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();

    actuarComoUsuarioDeTenant();
    colaboradorConCuentaDePlan();

    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 2])->assertForbidden();
    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 2, 'comentario' => 'x'])->assertForbidden();
});

it('editar el plan después del acuerdo borra el acuerdo y la aceptación ya no es posible con la versión anterior', function () {
    [$id, $adminId, $fichaId] = pdiParaAcordar();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();
    $this->putJson("/api/pdi/{$id}", pdiCuerpo($adminId, ['colaborador_id' => $fichaId, 'objetivo' => 'Cambio']) + ['version' => 2])->assertOk();

    actuarComoColaboradorDelPlan($fichaId);
    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 2])->assertNotFound();
});

it('el acuerdo con una versión desfasada responde 409', function () {
    [$id] = pdiParaAcordar();

    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 9])->assertStatus(409)->assertJsonPath('version_actual', 1);
});

it('solo el administrador confirma el acuerdo y la versión es obligatoria', function () {
    [$id] = pdiParaAcordar();
    $this->postJson("/api/pdi/{$id}/acuerdo", [])->assertUnprocessable()->assertJsonValidationErrors('version');

    actuarComoUsuarioDeTenant();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertForbidden();
    $this->postJson('/api/pdi/999/aceptar', ['version' => 1])->assertStatus(404);
});
