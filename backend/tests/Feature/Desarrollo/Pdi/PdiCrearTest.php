<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

it('crea un plan en Borrador con código PDI-0001, responsable y datos de creación', function () {
    $admin = pdiComoAdmin();

    $r = $this->postJson('/api/pdi', pdiCuerpo($admin->id));

    $r->assertCreated()
        ->assertJsonPath('data.codigo', 'PDI-0001')
        ->assertJsonPath('data.estado', 'Borrador')
        ->assertJsonPath('data.tipo', 'Mejora')
        ->assertJsonPath('data.responsable.id', $admin->id)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.compromisos.0.nivel_actual', 'C')
        ->assertJsonPath('data.compromisos.0.nivel_objetivo', 'B')
        ->assertJsonPath('data.compromisos.0.acciones.0.estado', 'Pendiente')
        ->assertJsonPath('data.acuerdo_responsable', null)
        ->assertJsonPath('data.acciones_permitidas.editar', true)
        ->assertJsonPath('data.acciones_permitidas.acordar', true);
    expect($r->json('data.creado_por'))->not->toBeNull()->and($r->json('data.creado_en'))->not->toBeNull();
    expect(PdiHistorialModel::query()->where('tipo', 'Creacion')->count())->toBe(1);
});

it('el responsable es obligatorio en el cuerpo (la interfaz propone a quien crea)', function () {
    $admin = pdiComoAdmin();

    $this->postJson('/api/pdi', collect(pdiCuerpo($admin->id))->except('responsable_id')->all())->assertUnprocessable()->assertJsonValidationErrors('responsable_id');
});

it('Carrera y Talento exigen puesto objetivo y Mejora no lo admite', function () {
    $admin = pdiComoAdmin();

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['tipo' => 'Carrera']))->assertUnprocessable()->assertJsonValidationErrors('puesto_objetivo_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['tipo' => 'Talento']))->assertUnprocessable()->assertJsonValidationErrors('puesto_objetivo_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['puesto_objetivo_id' => 2]))->assertUnprocessable()->assertJsonValidationErrors('puesto_objetivo_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['tipo' => 'Carrera', 'puesto_objetivo_id' => 99]))->assertUnprocessable()->assertJsonValidationErrors('puesto_objetivo_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['tipo' => 'Carrera', 'puesto_objetivo_id' => 2]))->assertCreated()->assertJsonPath('data.puesto_objetivo.id', 2);
});

it('no permite un segundo plan no Cerrado del mismo tipo y sí tras cerrar el primero', function () {
    $admin = pdiComoAdmin();
    $primero = $this->postJson('/api/pdi', pdiCuerpo($admin->id))->assertCreated();

    $this->postJson('/api/pdi', pdiCuerpo($admin->id))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'plan_duplicado')
        ->assertJsonPath('plan_existente.codigo', 'PDI-0001')
        ->assertJsonValidationErrors('tipo');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['tipo' => 'Carrera', 'puesto_objetivo_id' => 2]))->assertCreated();
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['colaborador_id' => 11]))->assertCreated();

    PdiModel::query()->whereKey($primero->json('data.id'))->update(['estado' => EstadoDePdi::Cerrado->value]);

    $this->postJson('/api/pdi', pdiCuerpo($admin->id))->assertCreated()->assertJsonPath('data.codigo', 'PDI-0004');
});

it('rechaza elementos inactivos, inexistentes o repetidos', function () {
    $admin = pdiComoAdmin();
    $base = pdiCuerpo($admin->id)['compromisos'][0];

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => [array_replace($base, ['elemento_id' => 9])]]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.elemento_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => [array_replace($base, ['elemento_id' => 77])]]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.elemento_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => [$base, $base]]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.1.elemento_id');
});

it('rechaza un responsable que no es administrador activo', function () {
    $admin = pdiComoAdmin();

    $this->postJson('/api/pdi', pdiCuerpo(999))->assertUnprocessable()->assertJsonValidationErrors('responsable_id');
});

it('rechaza fechas fuera del periodo o invertidas', function () {
    $admin = pdiComoAdmin();
    $base = pdiCuerpo($admin->id)['compromisos'][0];

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['fecha_fin' => '2026-06-01']))->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => [array_replace($base, ['fecha_fin' => '2027-03-01'])]]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.fecha_inicio');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['hitos' => [['fecha' => '2027-03-01', 'descripcion' => 'x']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('hitos.0.fecha');
});

it('rechaza un colaborador sin vínculo vigente y la dimensión Conocimiento desactivada', function () {
    $admin = pdiComoAdmin();
    $base = pdiCuerpo($admin->id)['compromisos'][0];

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['colaborador_id' => 99]))->assertUnprocessable()->assertJsonValidationErrors('colaborador_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => [array_replace($base, ['dimension' => 'Conocimiento', 'tipo_elemento' => 'Conocimiento'])]]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.dimension');
});

it('rechaza un plan_accion_id de un tipo no vinculable y cualquier vínculo mientras no exista la lectura de vinculables', function () {
    $admin = pdiComoAdmin();
    $base = pdiCuerpo($admin->id)['compromisos'][0];
    $conVinculo = fn (string $tipo) => [array_replace($base, ['acciones' => [array_replace($base['acciones'][0], ['tipo' => $tipo, 'plan_accion_id' => 55])]])];

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => $conVinculo('Coaching')]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.acciones.0.plan_accion_id');
    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => $conVinculo('Curso')]))
        ->assertUnprocessable()->assertJsonValidationErrors('compromisos.0.acciones.0.plan_accion_id');
});

it('se puede guardar sin compromisos ni acciones', function () {
    $admin = pdiComoAdmin();

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['compromisos' => [], 'hitos' => []]))
        ->assertCreated()
        ->assertJsonPath('data.compromisos', [])
        ->assertJsonPath('data.completitud', ['sin_compromisos']);
});

it('valida los campos obligatorios con mensajes en español', function () {
    pdiComoAdmin();

    $this->postJson('/api/pdi', [])->assertUnprocessable()->assertJsonValidationErrors(['colaborador_id', 'tipo', 'objetivo', 'responsable_id', 'fecha_inicio', 'fecha_fin']);
});

it('el colaborador no puede crear y sin sesión responde 401; no existe DELETE', function () {
    $this->postJson('/api/pdi', [])->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    pdiFalsos();
    $this->postJson('/api/pdi', pdiCuerpo(1))->assertForbidden();

    $admin = pdiComoAdmin();
    $id = $this->postJson('/api/pdi', pdiCuerpo($admin->id))->json('data.id');
    $this->deleteJson("/api/pdi/{$id}")->assertStatus(405);
    expect(PdiModel::query()->count())->toBe(1);
});

it('el tipo creado queda guardado y es inmutable en el modelo', function () {
    $admin = pdiComoAdmin();

    $this->postJson('/api/pdi', pdiCuerpo($admin->id, ['tipo' => 'Talento', 'puesto_objetivo_id' => 3]))->assertCreated();

    expect(PdiModel::query()->first()->tipo)->toBe(TipoDePdi::Talento->value);
});
