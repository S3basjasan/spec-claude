<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

function pdiPlanConAcuerdo(int $adminId): array
{
    $id = test()->postJson('/api/pdi', pdiCuerpo($adminId))->assertCreated()->json('data.id');
    PdiModel::query()->whereKey($id)->update(['acuerdo_responsable_at' => '2026-06-10 09:00:00', 'acuerdo_responsable_by' => $adminId, 'aceptacion_colaborador_at' => '2026-06-11 09:00:00']);

    return [$id, test()->getJson("/api/pdi/{$id}")->json('data')];
}

it('edita un Borrador: reemplaza todo, borra los acuerdos e incrementa la versión', function () {
    $admin = pdiComoAdmin();
    [$id] = pdiPlanConAcuerdo($admin->id);

    $cuerpo = pdiCuerpo($admin->id, ['objetivo' => 'Nuevo objetivo', 'hitos' => []]) + ['version' => 1];
    $cuerpo['compromisos'][0]['acciones'][] = ['tipo' => 'Coaching', 'descripcion' => 'Sesiones de coaching', 'persona_cargo' => 'Marta', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-10-01'];

    $r = $this->putJson("/api/pdi/{$id}", $cuerpo)->assertOk();

    expect($r->json('data.objetivo'))->toBe('Nuevo objetivo')
        ->and($r->json('data.version'))->toBe(2)
        ->and($r->json('data.codigo'))->toBe('PDI-0001')
        ->and($r->json('data.compromisos.0.acciones'))->toHaveCount(2)
        ->and($r->json('data.hitos'))->toBe([])
        ->and($r->json('data.acuerdo_responsable'))->toBeNull()
        ->and($r->json('data.aceptacion_colaborador'))->toBeNull();
    expect(PdiHistorialModel::query()->where('tipo', 'Edicion')->count())->toBe(1);
});

it('el tipo no se puede cambiar', function () {
    $admin = pdiComoAdmin();
    [$id] = pdiPlanConAcuerdo($admin->id);

    $this->putJson("/api/pdi/{$id}", pdiCuerpo($admin->id, ['tipo' => 'Carrera', 'puesto_objetivo_id' => 2]) + ['version' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->putJson("/api/pdi/{$id}", pdiCuerpo($admin->id, ['tipo' => 'Mejora']) + ['version' => 1])->assertOk();
});

it('un Acordado o un Cerrado no se editan', function () {
    $admin = pdiComoAdmin();
    [$id] = pdiPlanConAcuerdo($admin->id);

    foreach ([EstadoDePdi::Acordado, EstadoDePdi::Cerrado] as $estado) {
        PdiModel::query()->whereKey($id)->update(['estado' => $estado->value]);
        $this->putJson("/api/pdi/{$id}", pdiCuerpo($admin->id) + ['version' => 1])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    }
});

it('una versión desfasada responde 409 y no cambia nada', function () {
    $admin = pdiComoAdmin();
    [$id] = pdiPlanConAcuerdo($admin->id);

    $this->putJson("/api/pdi/{$id}", pdiCuerpo($admin->id, ['objetivo' => 'Otro']) + ['version' => 7])
        ->assertStatus(409)->assertJsonPath('code', 'version_desfasada')->assertJsonPath('version_actual', 1);
    expect(PdiModel::query()->find($id)->objetivo)->not->toBe('Otro');
});

it('la edición valida igual que la creación', function () {
    $admin = pdiComoAdmin();
    [$id] = pdiPlanConAcuerdo($admin->id);
    $base = pdiCuerpo($admin->id)['compromisos'][0];

    $this->putJson("/api/pdi/{$id}", pdiCuerpo($admin->id, ['compromisos' => [array_replace($base, ['elemento_id' => 9])]]) + ['version' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.elemento_id');
    $this->putJson("/api/pdi/{$id}", pdiCuerpo(999) + ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('responsable_id');
});

it('devuelve 404 si el plan no existe o es de otro tenant, y el colaborador no puede editar', function () {
    $admin = pdiComoAdmin();
    $this->putJson('/api/pdi/999', pdiCuerpo($admin->id) + ['version' => 1])->assertNotFound();

    tenantDos();
    $ajeno = pdiEnBd(['tenant' => 2]);
    $this->putJson("/api/pdi/{$ajeno->id}", pdiCuerpo($admin->id) + ['version' => 1])->assertNotFound();

    actuarComoUsuarioDeTenant();
    $this->putJson('/api/pdi/1', pdiCuerpo($admin->id) + ['version' => 1])->assertForbidden();
});

it('el detalle muestra el plan al administrador y 404 al colaborador ajeno o con un Borrador sin acuerdo', function () {
    $admin = pdiComoAdmin();
    $id = $this->postJson('/api/pdi', pdiCuerpo($admin->id))->json('data.id');

    $this->getJson("/api/pdi/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    actuarComoUsuarioDeTenant();
    $this->getJson("/api/pdi/{$id}")->assertNotFound();
});
