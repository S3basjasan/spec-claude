<?php

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use Tests\Support\Desarrollo\NombresDeReferenciaFalsos;

it('precarga Mejora desde la DNF más reciente con niveles y origen', function () {
    pdiComoAdmin();
    dnfDeEjemplo([10 => [[1, 'A', 'D']]]);
    $dnf = dnfDeEjemplo([10 => [[1, 'B', 'C'], [2, 'B', 'B'], [3, 'A', 'D']]]);

    $r = $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Mejora')->assertOk();

    expect($r->json('data.origen'))->toMatchArray(['tipo' => 'dnf', 'id' => $dnf->id])
        ->and($r->json('data.compromisos'))->toHaveCount(2)
        ->and($r->json('data.compromisos.0'))->toMatchArray([
            'dimension' => 'Competencia', 'tipo_elemento' => 'Competencia', 'nivel_actual' => 'C', 'nivel_objetivo' => 'B',
        ])
        ->and($r->json('data.compromisos.0.elemento'))->toMatchArray(['id' => 1, 'nombre' => 'Competencia 1'])
        ->and($r->json('data.compromisos.1.elemento.id'))->toBe(3)
        ->and($r->json('data.avisos'))->toBe([]);
});

it('precarga Carrera desde la última adecuación al puesto objetivo, con la experiencia como compromiso sin niveles', function () {
    pdiComoAdmin();
    $adecuacion = adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'C'],
        ['anios' => [5.0, 3.0], 'tiempo' => 18],
    ], ['experiencia' => 5.0]);

    $r = $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Carrera&puesto_objetivo_id=2')->assertOk();

    expect($r->json('data.origen'))->toMatchArray(['tipo' => 'adecuacion', 'id' => $adecuacion->id])
        ->and($r->json('data.compromisos'))->toHaveCount(2)
        ->and($r->json('data.compromisos.0'))->toMatchArray(['dimension' => 'Competencia', 'nivel_actual' => 'C', 'nivel_objetivo' => 'B'])
        ->and($r->json('data.compromisos.1'))->toMatchArray(['dimension' => 'Experiencia', 'nivel_actual' => null, 'nivel_objetivo' => null, 'elemento' => null])
        ->and($r->json('data.compromisos.1.descripcion'))->toContain('5 años')->toContain('actual: 3 años');
});

it('omite con aviso los elementos inactivos', function () {
    pdiComoAdmin();
    app()->instance(NombresDeReferenciaInterface::class, new NombresDeReferenciaFalsos([3]));
    dnfDeEjemplo([10 => [[1, 'B', 'C'], [3, 'A', 'D']]]);

    $r = $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Mejora')->assertOk();

    expect($r->json('data.compromisos'))->toHaveCount(1)
        ->and($r->json('data.avisos.0'))->toMatchArray(['tipo' => 'inactivos_omitidos', 'cantidad' => 1]);
});

it('con la dimensión Conocimiento desactivada no genera conocimientos y lo avisa', function () {
    pdiComoAdmin();
    adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['dim' => 'Conocimiento', 'id' => 5, 'objetivo' => 'B', 'valor' => 'D', 'tiempo' => 6],
    ]);

    $r = $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Talento&puesto_objetivo_id=2')->assertOk();

    expect($r->json('data.compromisos'))->toHaveCount(1)
        ->and(collect($r->json('data.avisos'))->pluck('tipo')->all())->toBe(['conocimientos_desactivados']);
});

it('devuelve una lista vacía sin DNF ni adecuación, y exige el puesto en Carrera y Talento', function () {
    pdiComoAdmin();

    $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Mejora')->assertOk()->assertJsonPath('data.origen', null)->assertJsonPath('data.compromisos', []);
    $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Carrera&puesto_objetivo_id=2')->assertOk()->assertJsonPath('data.compromisos', []);
    $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Carrera')->assertUnprocessable()->assertJsonValidationErrors('puesto_objetivo_id');
    $this->getJson('/api/pdi/precarga?tipo=Mejora')->assertUnprocessable()->assertJsonValidationErrors('colaborador_id');
});

it('solo el administrador puede precargar', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/pdi/precarga?colaborador_id=10&tipo=Mejora')->assertForbidden();
});

it('las opciones listan colaboradores, puestos, departamentos, responsables y dimensiones; con colaborador, el puesto sugerido y planes abiertos', function () {
    $admin = pdiComoAdmin();
    $creado = $this->postJson('/api/pdi', pdiCuerpo($admin->id))->assertCreated();

    $general = $this->getJson('/api/pdi/opciones')->assertOk();
    expect($general->json('data.colaboradores'))->toHaveCount(4)
        ->and($general->json('data.colaboradores.0'))->toHaveKeys(['id', 'codigo', 'nombre', 'departamento'])
        ->and($general->json('data.puestos'))->not->toBeEmpty()
        ->and($general->json('data.departamentos'))->not->toBeEmpty()
        ->and($general->json('data.responsables.0.id'))->toBe($admin->id)
        ->and($general->json('data.dimensiones_disponibles'))->toBe(['Competencia', 'Experiencia', 'Meta'])
        ->and(collect($general->json('data.competencias'))->pluck('id')->all())->toBe([1, 2, 3]);

    $filtrado = $this->getJson('/api/pdi/opciones?q=Luis')->assertOk();
    expect($filtrado->json('data.colaboradores'))->toHaveCount(1);

    $deColaborador = $this->getJson('/api/pdi/opciones?colaborador_id=10')->assertOk();
    expect($deColaborador->json('data.plan_abierto.Mejora.codigo'))->toBe($creado->json('data.codigo'))
        ->and($deColaborador->json('data.plan_abierto.Carrera'))->toBeNull()
        ->and($deColaborador->json('data.puesto_objetivo_sugerido'))->toBeNull();
});
