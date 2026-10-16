<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->creador = actuarComoAdminDeTenant();
    $this->original = UserModel::factory()->admin()->create(['tenant_id' => 1]);
    $this->nuevo = UserModel::factory()->admin()->create(['tenant_id' => 1]);
    foreach ([$this->creador, $this->original, $this->nuevo] as $u) {
        $this->falsos['aprobadores']->administradores[$u->id] = $u->name;
    }
    $this->enRevision = function () {
        $plan = planGuardado(['aprobador_id' => $this->original->id], actorId: $this->creador->id);
        forzarEstadoPlan($plan['id'], 'Revision');

        return $plan['id'];
    };
    $this->reasignar = fn (int $id, ?int $aprobador) => $this->patchJson("/api/planes/{$id}/aprobador", ['aprobador_id' => $aprobador]);
});

it('permite a cualquier administrador reasignar el aprobador de un plan en Revisión sin cambiar su estado', function () {
    $id = ($this->enRevision)();

    ($this->reasignar)($id, $this->nuevo->id)->assertOk()
        ->assertJsonPath('data.estado', 'Revision')
        ->assertJsonPath('data.aprobador.id', $this->nuevo->id)
        ->assertJsonPath('data.fecha_aprobacion', null);

    $renglon = PlanHistorialModel::query()->where('plan_id', $id)->sole();
    expect($renglon->tipo)->toBe('Aprobador')
        ->and($renglon->valor_anterior)->toBe($this->original->name)
        ->and($renglon->valor_nuevo)->toBe($this->nuevo->name)
        ->and((int) $renglon->user_id)->toBe($this->creador->id);
});

it('el nuevo aprobador puede aprobar y el anterior ya no', function () {
    $id = ($this->enRevision)();
    ($this->reasignar)($id, $this->nuevo->id)->assertOk();

    Sanctum::actingAs($this->original, ['admin']);
    $this->patchJson("/api/planes/{$id}/estado", ['estado' => 'Aprobado'])->assertForbidden();
    Sanctum::actingAs($this->nuevo, ['admin']);
    $this->patchJson("/api/planes/{$id}/estado", ['estado' => 'Aprobado'])->assertOk()->assertJsonPath('data.estado', 'Aprobado');
});

it('solo se puede reasignar en Revisión', function (string $estado) {
    $id = ($this->enRevision)();
    forzarEstadoPlan($id, $estado);

    ($this->reasignar)($id, $this->nuevo->id)->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    expect((int) PlanModel::query()->find($id)->aprobador_id)->toBe($this->original->id);
})->with(['Borrador', 'Aprobado', 'Ejecutando', 'Cerrado']);

it('rechaza un aprobador que no es administrador activo', function () {
    $id = ($this->enRevision)();

    ($this->reasignar)($id, 9999)->assertUnprocessable()->assertJsonPath('code', 'aprobador_invalido');
    ($this->reasignar)($id, null)->assertUnprocessable();
});

it('exige rol de administrador y respeta el tenant', function () {
    $id = ($this->enRevision)();

    actuarComoUsuarioDeTenant();
    ($this->reasignar)($id, $this->nuevo->id)->assertForbidden();
    actuarComoAdminDeTenant(2);
    ($this->reasignar)($id, $this->nuevo->id)->assertNotFound();
});
