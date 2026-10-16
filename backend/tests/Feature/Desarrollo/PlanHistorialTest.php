<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->creador = actuarComoAdminDeTenant();
    $this->aprobador = UserModel::factory()->admin()->create(['tenant_id' => 1, 'name' => 'Marta Ríos']);
    $this->falsos['aprobadores']->administradores[$this->creador->id] = $this->creador->name;
    $this->falsos['aprobadores']->administradores[$this->aprobador->id] = $this->aprobador->name;
    $this->plan = planGuardado(['aprobador_id' => $this->aprobador->id], actorId: $this->creador->id);
    $this->estado = fn (string $estado, ?string $motivo = null) => $this->patchJson('/api/planes/'.$this->plan['id'].'/estado', array_filter(['estado' => $estado, 'motivo' => $motivo]));
});

it('registra y devuelve el historial más reciente primero, con usuario, fecha, estados y motivo', function () {
    ($this->estado)('Revision')->assertOk();
    Sanctum::actingAs($this->aprobador, ['admin']);
    ($this->estado)('Borrador', 'Falta detalle')->assertOk();
    Sanctum::actingAs($this->creador, ['admin']);
    ($this->estado)('Revision')->assertOk();

    $respuesta = $this->getJson('/api/planes/'.$this->plan['id'].'/historial')->assertOk();

    expect($respuesta->json('meta.total'))->toBe(3)
        ->and($respuesta->json('data.0.valor_nuevo'))->toBe('Revision')
        ->and($respuesta->json('data.1.valor_anterior'))->toBe('Revision')
        ->and($respuesta->json('data.1.valor_nuevo'))->toBe('Borrador')
        ->and($respuesta->json('data.1.motivo'))->toBe('Falta detalle')
        ->and($respuesta->json('data.1.usuario.nombre'))->toBe('Marta Ríos')
        ->and($respuesta->json('data.2.tipo'))->toBe('Estado')
        ->and($respuesta->json('data.2.fecha'))->not->toBeNull();
});

it('incluye participantes, cancelaciones y reasignaciones desde la aprobación, y no los cambios en Borrador', function () {
    $accion = $this->plan['acciones'][0]['id'];
    $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => [12]])->assertOk();
    $this->getJson('/api/planes/'.$this->plan['id'].'/historial')->assertJsonPath('meta.total', 0);

    forzarEstadoPlan($this->plan['id'], 'Aprobado');
    $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => [12, 13]])->assertOk();
    $this->patchJson("/api/plan-acciones/{$accion}/cancelar", ['motivo' => 'Sin cupo'])->assertOk();

    $datos = $this->getJson('/api/planes/'.$this->plan['id'].'/historial')->assertOk()->json('data');

    expect(collect($datos)->pluck('tipo')->all())->toBe(['Cancelacion', 'Participante'])
        ->and($datos[0]['accion_nombre'])->toBe('Análisis con SQL')
        ->and($datos[0]['motivo'])->toBe('Sin cupo')
        ->and($datos[1]['valor_nuevo'])->toBe('Colaborador 13')
        ->and($datos[1]['accion_id'])->toBe($accion);

    forzarEstadoPlan($this->plan['id'], 'Revision');
    $this->patchJson('/api/planes/'.$this->plan['id'].'/aprobador', ['aprobador_id' => $this->creador->id])->assertOk();
    expect($this->getJson('/api/planes/'.$this->plan['id'].'/historial')->json('data.0.tipo'))->toBe('Aprobador');
});

it('pagina el historial', function () {
    ($this->estado)('Revision')->assertOk();
    Sanctum::actingAs($this->aprobador, ['admin']);
    ($this->estado)('Borrador', 'x')->assertOk();

    $this->getJson('/api/planes/'.$this->plan['id'].'/historial?per_page=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/planes/'.$this->plan['id'].'/historial?per_page=500')->assertUnprocessable();
});

it('el plan expone quién lo creó y modificó por última vez y cuándo', function () {
    ($this->estado)('Revision')->assertOk();

    $this->getJson('/api/planes/'.$this->plan['id'])->assertOk()
        ->assertJsonPath('data.creado_por.id', $this->creador->id)
        ->assertJsonPath('data.modificado_por.id', $this->creador->id)
        ->assertJsonPath('data.puede.editar', false);
});

it('responde 404 para planes inexistentes o de otro tenant y 403 a un colaborador', function () {
    $this->getJson('/api/planes/9999/historial')->assertNotFound();
    actuarComoAdminDeTenant(2);
    $this->getJson('/api/planes/'.$this->plan['id'].'/historial')->assertNotFound();
    actuarComoUsuarioDeTenant();
    $this->getJson('/api/planes/'.$this->plan['id'].'/historial')->assertForbidden();
});
