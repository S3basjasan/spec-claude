<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

/** @return int id de la ficha del colaborador que actúa */
function pdiColaboradorActuando(): int
{
    pdiFalsos();
    [, $fichaId] = colaboradorConCuentaDePlan();

    return $fichaId;
}

it('devuelve solo los planes propios con acuerdo del responsable y excluye el Borrador sin acuerdo', function () {
    $ficha = pdiColaboradorActuando();
    pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Borrador, 'acuerdo' => false]);
    $conAcuerdo = pdiEnBd(['colaborador' => $ficha, 'tipo' => TipoDePdi::Carrera, 'estado' => EstadoDePdi::Borrador, 'acuerdo' => true]);
    $acordado = pdiEnBd(['colaborador' => $ficha, 'tipo' => TipoDePdi::Talento, 'puesto' => 3, 'estado' => EstadoDePdi::Acordado]);
    pdiEnBd(['colaborador' => $ficha + 50, 'estado' => EstadoDePdi::Acordado]);

    $r = $this->getJson('/api/pdi/mi-plan')->assertOk();

    expect(collect($r->json('data.planes'))->pluck('id')->all())->toBe([$acordado->id, $conAcuerdo->id])
        ->and($r->json('data.planes.1.acciones_permitidas.aceptar'))->toBeTrue()
        ->and($r->json('data.planes.0.acciones_permitidas.aceptar'))->toBeFalse();
});

it('«Qué sigue» lista acciones e hitos pendientes de planes Acordados, ordenados por fecha con los vencidos primero', function () {
    $ficha = pdiColaboradorActuando();
    $acordado = pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Acordado, 'acciones' => ['Pendiente', 'Cumplida'], 'hitos' => [['2026-06-01'], ['2026-08-01'], ['2026-05-01', 'Realizado']]]);
    pdiEnBd(['colaborador' => $ficha, 'tipo' => TipoDePdi::Carrera, 'estado' => EstadoDePdi::Borrador, 'acuerdo' => true]);

    $sigue = $this->getJson('/api/pdi/mi-plan')->assertOk()->json('data.que_sigue');

    expect($sigue)->toHaveCount(3)
        ->and(collect($sigue)->pluck('fecha')->all())->toBe(['2026-06-01', '2026-08-01', '2026-09-30'])
        ->and(collect($sigue)->pluck('tipo')->all())->toBe(['hito', 'hito', 'accion'])
        ->and(collect($sigue)->pluck('vencido')->all())->toBe([true, false, false])
        ->and($sigue[0]['plan_codigo'])->toBe($acordado->codigo)
        ->and($sigue[2]['actividad_id'])->toBeNull();
});

it('responde vacío si el usuario no tiene ficha o no tiene planes', function () {
    pdiFalsos();
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/pdi/mi-plan')->assertOk()->assertJsonPath('data.planes', [])->assertJsonPath('data.que_sigue', []);

    pdiColaboradorActuando();
    $this->getJson('/api/pdi/mi-plan')->assertOk()->assertJsonPath('data.planes', []);
});

it('el administrador no puede usar mi-plan y sin sesión responde 401', function () {
    $this->getJson('/api/pdi/mi-plan')->assertUnauthorized();

    actuarComoAdminDeTenant();
    $this->getJson('/api/pdi/mi-plan')->assertForbidden();
});

it('el colaborador no ve el plan de otra persona por el detalle ni por el historial', function () {
    $ficha = pdiColaboradorActuando();
    $ajeno = pdiEnBd(['colaborador' => $ficha + 50, 'estado' => EstadoDePdi::Acordado]);
    $propio = pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Acordado]);

    $this->getJson("/api/pdi/{$ajeno->id}")->assertNotFound();
    $this->getJson("/api/pdi/{$propio->id}")->assertOk()->assertJsonPath('data.acciones_permitidas.editar', false);
});
