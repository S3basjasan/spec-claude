<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

/** @return int id de la ficha del colaborador que actúa */
function pdiColaboradorPrivado(): int
{
    pdiFalsos();
    [, $fichaId] = colaboradorConCuentaDePlan();

    return $fichaId;
}

it('el colaborador no recibe un Borrador sin acuerdo del responsable ni por el detalle ni por el historial', function () {
    $ficha = pdiColaboradorPrivado();
    $borrador = pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Borrador, 'acuerdo' => false]);

    $this->getJson("/api/pdi/{$borrador->id}")->assertNotFound();
    $this->getJson("/api/pdi/{$borrador->id}/historial")->assertNotFound();
    expect($this->getJson('/api/pdi/mi-plan')->assertOk()->json('data.planes'))->toBe([]);
});

it('el colaborador nunca recibe planes de otras personas', function () {
    $ficha = pdiColaboradorPrivado();
    $ajeno = pdiEnBd(['colaborador' => $ficha + 50, 'estado' => EstadoDePdi::Acordado]);
    $propio = pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Acordado]);

    $this->getJson("/api/pdi/{$ajeno->id}")->assertNotFound();
    $this->getJson("/api/pdi/{$ajeno->id}/historial")->assertNotFound();
    $this->postJson("/api/pdi/{$ajeno->id}/aceptar", ['version' => 1])->assertForbidden();
    expect(collect($this->getJson('/api/pdi/mi-plan')->json('data.planes'))->pluck('id')->all())->toBe([$propio->id]);
});

it('el colaborador no accede a las pantallas ni acciones de gestión', function () {
    $ficha = pdiColaboradorPrivado();
    $propio = pdiEnBd(['colaborador' => $ficha, 'tipo' => TipoDePdi::Mejora, 'estado' => EstadoDePdi::Acordado]);

    foreach (['/api/pdi', '/api/pdi/sin-plan', '/api/pdi/gantt', '/api/pdi/opciones', '/api/pdi/acciones-vinculables?colaborador_id='.$ficha] as $ruta) {
        $this->getJson($ruta)->assertForbidden();
    }
    $this->postJson('/api/pdi', [])->assertForbidden();
    $this->putJson("/api/pdi/{$propio->id}", [])->assertForbidden();
    $this->postJson("/api/pdi/{$propio->id}/acuerdo", ['version' => 1])->assertForbidden();
    $this->postJson("/api/pdi/{$propio->id}/cerrar", [])->assertForbidden();
    $this->patchJson("/api/pdi/{$propio->id}/responsable", [])->assertForbidden();
});

it('el detalle propio no ofrece ninguna acción de gestión al colaborador', function () {
    $ficha = pdiColaboradorPrivado();
    $propio = pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Acordado]);

    $permitidas = $this->getJson("/api/pdi/{$propio->id}")->assertOk()->json('data.acciones_permitidas');

    foreach (['editar', 'acordar', 'devolver', 'avance', 'vincular', 'cerrar', 'cambiar_responsable'] as $accion) {
        expect($permitidas[$accion])->toBeFalse();
    }
});

it('los errores no revelan trazas, rutas ni datos de otros módulos', function () {
    $ficha = pdiColaboradorPrivado();
    $ajeno = pdiEnBd(['colaborador' => $ficha + 50, 'estado' => EstadoDePdi::Acordado]);

    $respuestas = [
        $this->getJson("/api/pdi/{$ajeno->id}"),
        $this->getJson('/api/pdi/999999'),
        $this->getJson('/api/pdi'),
    ];

    foreach ($respuestas as $respuesta) {
        $texto = json_encode($respuesta->json(), JSON_UNESCAPED_UNICODE);
        expect($texto)->not->toContain('trace')->not->toContain('vendor')->not->toContain('SQLSTATE')->not->toContain('App\\\\');
    }
});

it('un plan de otro tenant no se revela ni por existencia', function () {
    pdiComoAdmin();
    $deOtro = pdiEnBd(['tenant' => 2, 'colaborador' => 10]);

    $this->getJson("/api/pdi/{$deOtro->id}")->assertNotFound();
    expect($this->getJson('/api/pdi')->assertOk()->json('data'))->toBe([]);
});
