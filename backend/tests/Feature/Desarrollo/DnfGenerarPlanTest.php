<?php

use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;
use Tests\Support\Desarrollo\PlanDeFormacionFalso;

it('crea el borrador, registra quién y cuándo y devuelve la referencia', function () {
    $dobles = dnfFalsos();
    $admin = actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', 'B'], [3, 'B', null]]]);

    $respuesta = $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertCreated();

    expect($respuesta->json('data.plan_formacion_id'))->toBe(31)
        ->and($respuesta->json('data.generado_por.id'))->toBe($admin->id)
        ->and($respuesta->json('data.generado_en'))->not->toBeEmpty();
    $guardada = DnfModel::query()->findOrFail($dnf->id);
    expect((int) $guardada->plan_formacion_id)->toBe(31)
        ->and((int) $guardada->plan_generado_por)->toBe($admin->id)
        ->and($guardada->plan_generado_en)->not->toBeNull();
    expect($dobles['plan']->solicitudes)->toHaveCount(1)
        ->and($dobles['plan']->solicitudes[0]['dnf_id'])->toBe($dnf->id)
        ->and($dobles['plan']->solicitudes[0]['tenant_id'])->toBe(1)
        ->and($dobles['plan']->solicitudes[0]['actor_id'])->toBe($admin->id);
});

it('envía una sola solicitud con las brechas de todos los colaboradores de una masiva', function () {
    $dobles = dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']], 11 => [[1, 'A', 'D'], [2, 'B', 'B']], 12 => [[1, 'B', 'B']]], 'Departamento', 5);

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertCreated();

    $solicitudes = $dobles['plan']->solicitudes;
    expect($solicitudes)->toHaveCount(1);
    expect(collect($solicitudes[0]['brechas'])->map(fn ($b) => [$b['colaborador_id'], $b['competencia_id'], $b['prioridad']])->all())
        ->toBe([[10, 1, 'Alta'], [11, 1, 'Critica']]);
});

it('rechaza una DNF sin brechas', function () {
    $dobles = dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'B', 'B'], [2, 'C', 'A'], [3, 'B', null]]]);

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertUnprocessable()->assertJsonPath('message', 'La DNF no tiene brechas.');

    expect($dobles['plan']->solicitudes)->toBe([])
        ->and(DnfModel::query()->findOrFail($dnf->id)->plan_formacion_id)->toBeNull();
});

it('rechaza una segunda solicitud: una DNF genera como máximo un plan', function () {
    $dobles = dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertCreated();
    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertUnprocessable()->assertJsonPath('message', 'La DNF ya generó un plan.');

    expect($dobles['plan']->solicitudes)->toHaveCount(1)
        ->and((int) DnfModel::query()->findOrFail($dnf->id)->plan_formacion_id)->toBe(31);
});

it('si el plan fue asignado en paralelo, la asignación atómica evita sobrescribirlo', function () {
    dnfFalsos(['plan' => new PlanDeFormacionFalso(32)]);
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);
    // Otra petición asignó el plan después de que esta lo comprobó.
    app()->bind(PlanDeFormacionInterface::class, fn () => new class(33) extends PlanDeFormacionFalso
    {
        public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int
        {
            app(DnfRepositoryInterface::class)->asignarPlan($tenantId, $dnfId, 99, $actorId, new DateTimeImmutable);

            return parent::crearBorrador($tenantId, $actorId, $dnfId, $brechas);
        }
    });

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertUnprocessable()->assertJsonPath('message', 'La DNF ya generó un plan.');

    expect((int) DnfModel::query()->findOrFail($dnf->id)->plan_formacion_id)->toBe(99);
});

it('si Plan de Formación no está disponible responde 503 y la DNF queda sin plan para reintentar', function () {
    dnfFalsos(['plan' => new PlanDeFormacionFalso(31, true)]);
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertStatus(503)->assertJsonPath('message', 'Plan de Formación no disponible.');

    expect(DnfModel::query()->findOrFail($dnf->id)->plan_formacion_id)->toBeNull();
    dnfFalsos(['plan' => new PlanDeFormacionFalso(31)]);
    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertCreated();
});

it('un fallo inesperado del módulo de planes se trata como no disponible, sin revelar detalles', function () {
    dnfFalsos();
    app()->bind(PlanDeFormacionInterface::class, fn () => new class extends PlanDeFormacionFalso
    {
        public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int
        {
            throw new RuntimeException('detalle interno secreto');
        }
    });
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $respuesta = $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertStatus(503);

    expect($respuesta->getContent())->not->toContain('secreto');
});

it('mantiene inmutables los datos de la DNF al asignar el plan', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', null]]]);
    $antes = DnfDetalleModel::query()->orderBy('id')->get()->toArray();

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertCreated();

    expect(DnfDetalleModel::query()->orderBy('id')->get()->toArray())->toBe($antes);
});

it('responde 404 para una DNF inexistente o de otro tenant', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    tenantDos();
    $ajena = dnfDeEjemplo([10 => [[1, 'A', 'C']]], tenantId: 2);

    $this->postJson('/api/dnf/9999/plan')->assertNotFound();
    $this->postJson('/api/dnf/'.$ajena->id.'/plan')->assertNotFound();
});

it('exige autenticación y rol de administrador', function () {
    dnfFalsos();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->postJson('/api/dnf/'.$dnf->id.'/plan')->assertForbidden();
});
