<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

it('muestra solo las acciones y los hitos de planes Acordados, con el estado de cada acción', function () {
    pdiComoAdmin();
    $acordado = pdiEnBd(['colaborador' => 10, 'estado' => EstadoDePdi::Acordado, 'acciones' => ['Cumplida', 'Pendiente']]);
    pdiEnBd(['colaborador' => 11, 'estado' => EstadoDePdi::Borrador, 'acuerdo' => true]);
    pdiEnBd(['colaborador' => 12, 'estado' => EstadoDePdi::Cerrado]);

    $r = $this->getJson('/api/pdi/gantt')->assertOk();

    expect($r->json('data'))->toHaveCount(1)
        ->and($r->json('data.0.plan.id'))->toBe($acordado->id)
        ->and($r->json('data.0.plan.avance'))->toBe(50)
        ->and(collect($r->json('data.0.acciones'))->pluck('estado')->all())->toBe(['Cumplida', 'Pendiente'])
        ->and($r->json('data.0.acciones.0'))->toHaveKeys(['id', 'compromiso', 'descripcion', 'tipo', 'fecha_inicio', 'fecha_fin', 'estado'])
        ->and($r->json('data.0.acciones.0.compromiso'))->toBe('Competencia 1')
        ->and($r->json('data.0.hitos'))->toHaveCount(1)
        ->and($r->json('hoy'))->toBe('2026-06-15')
        ->and($r->json('meta.total'))->toBe(1);
});

it('filtra por tipo de plan, responsable y departamento y pagina por plan', function () {
    pdiComoAdmin();
    $a = pdiEnBd(['colaborador' => 10, 'estado' => EstadoDePdi::Acordado, 'responsable' => 1]);
    $b = pdiEnBd(['colaborador' => 12, 'tipo' => TipoDePdi::Carrera, 'estado' => EstadoDePdi::Acordado, 'responsable' => 7]);
    $c = pdiEnBd(['colaborador' => 13, 'tipo' => TipoDePdi::Talento, 'puesto' => 3, 'estado' => EstadoDePdi::Acordado, 'responsable' => 7]);
    $ids = fn (string $q) => collect($this->getJson('/api/pdi/gantt?'.$q)->assertOk()->json('data'))->pluck('plan.id')->sort()->values()->all();

    expect($ids('tipo=Carrera'))->toBe([$b->id])
        ->and($ids('responsable_id=7'))->toBe(collect([$b->id, $c->id])->sort()->values()->all())
        ->and($ids('departamento_id=1'))->toBe([$a->id])
        ->and($ids('departamento_id=2&responsable_id=7&tipo=Talento'))->toBe([$c->id])
        ->and($this->getJson('/api/pdi/gantt?per_page=2')->json('meta.last_page'))->toBe(2);
    $this->getJson('/api/pdi/gantt?tipo=Otro')->assertUnprocessable();
});

it('responde vacío sin planes Acordados y exige el permiso del Gantt', function () {
    pdiComoAdmin();
    $this->getJson('/api/pdi/gantt')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/pdi/gantt')->assertForbidden();
});
