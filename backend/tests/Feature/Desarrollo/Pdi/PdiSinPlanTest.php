<?php

use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

it('lista a quienes tienen brecha de la última DNF y no tienen plan de Mejora abierto', function () {
    pdiComoAdmin();
    dnfDeEjemplo([10 => [[1, 'B', 'C'], [2, 'B', 'D']], 11 => [[1, 'B', 'C']], 12 => [[1, 'B', 'B']]], 'Departamento', 1);
    pdiEnBd(['colaborador' => 11]);

    $r = $this->getJson('/api/pdi/sin-plan')->assertOk();

    expect($r->json('data'))->toHaveCount(1)
        ->and($r->json('data.0'))->toMatchArray(['origen' => 'dnf', 'tipo_sugerido' => 'Mejora', 'brechas' => 2, 'puesto_objetivo' => null])
        ->and($r->json('data.0.colaborador.id'))->toBe(10)
        ->and($r->json('data.0.colaborador.departamento'))->not->toBeNull()
        ->and($r->json('meta.total'))->toBe(1);
});

it('lista a quienes tienen una adecuación con brechas y ningún plan de Carrera ni Talento abierto para ese puesto', function () {
    pdiComoAdmin();
    adecuacionEnBd([['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]], ['colaborador' => 12, 'puesto' => 2]);
    adecuacionEnBd([['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]], ['colaborador' => 13, 'puesto' => 2]);
    adecuacionEnBd([['id' => 1, 'objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11, 'puesto' => 2]);
    pdiEnBd(['colaborador' => 13, 'tipo' => TipoDePdi::Talento, 'puesto' => 2]);

    $r = $this->getJson('/api/pdi/sin-plan')->assertOk();

    expect($r->json('data'))->toHaveCount(1)
        ->and($r->json('data.0'))->toMatchArray(['origen' => 'adecuacion', 'tipo_sugerido' => 'Carrera', 'brechas' => 1])
        ->and($r->json('data.0.colaborador.id'))->toBe(12)
        ->and($r->json('data.0.puesto_objetivo.id'))->toBe(2);
});

it('deja de aparecer al crearle el plan y se filtra por departamento y página', function () {
    $admin = pdiComoAdmin();
    dnfDeEjemplo([10 => [[1, 'B', 'C']], 12 => [[1, 'B', 'C']]], 'Departamento', 1);

    expect($this->getJson('/api/pdi/sin-plan')->json('meta.total'))->toBe(2)
        ->and($this->getJson('/api/pdi/sin-plan?departamento_id=2')->json('data.0.colaborador.id'))->toBe(12)
        ->and($this->getJson('/api/pdi/sin-plan?per_page=1&page=2')->json('data'))->toHaveCount(1);

    $this->postJson('/api/pdi', pdiCuerpo($admin->id))->assertCreated();

    expect($this->getJson('/api/pdi/sin-plan')->json('meta.total'))->toBe(1);
});

it('solo el administrador con permiso de gestión ve el indicador', function () {
    pdiFalsos();
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/pdi/sin-plan')->assertForbidden();
});
