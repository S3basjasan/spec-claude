<?php

it('devuelve solo mis adecuaciones vigentes, una por puesto objetivo, la más reciente primero', function () {
    $yo = actuarComoColaboradorDeAdecuacion();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['colaborador' => $yo, 'puesto' => 2, 'creada' => '2026-01-01 10:00:00']);
    $vigente2 = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => $yo, 'puesto' => 2]);
    $vigente3 = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['colaborador' => $yo, 'puesto' => 3]);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11, 'puesto' => 2]); // de otra persona

    $d = $this->getJson('/api/adecuaciones/mi-adecuacion')->assertOk()->json('data');

    expect(array_column($d['adecuaciones'], 'id'))->toBe([$vigente3->id, $vigente2->id])
        ->and($d['seleccionada'])->toBe($vigente3->id)
        ->and($d['aviso'])->toBe('Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.')
        ->and($d['fuentes_no_disponibles'])->toBe([]);
    expect(array_column(array_column($d['adecuaciones'], 'puesto_objetivo'), 'id'))->toBe([3, 2]);
});

it('permite elegir el puesto objetivo y devuelve lista vacía sin adecuaciones', function () {
    $yo = actuarComoColaboradorDeAdecuacion();

    $vacio = $this->getJson('/api/adecuaciones/mi-adecuacion')->assertOk()->json('data');
    expect($vacio['adecuaciones'])->toBe([])->and($vacio['seleccionada'])->toBeNull();

    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['colaborador' => $yo, 'puesto' => 2, 'creada' => '2026-01-01 10:00:00']);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['colaborador' => $yo, 'puesto' => 3]);

    expect($this->getJson('/api/adecuaciones/mi-adecuacion?puesto_objetivo_id=2')->assertOk()->json('data.seleccionada'))->toBe($a->id);
});

it('trae el detalle por dimensión con tiempos de cierre y enlace al catálogo solo donde hay oferta', function () {
    $yo = actuarComoColaboradorDeAdecuacion([1]);
    adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['id' => 2, 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 9],
        ['anios' => [5.0, 3.0], 'tiempo' => 18],
        ['id' => 4, 'objetivo' => 'C', 'valor' => 'B'],
    ], ['colaborador' => $yo, 'experiencia' => 5.0]);

    $a = $this->getJson('/api/adecuaciones/mi-adecuacion')->assertOk()->json('data.adecuaciones.0');
    $brechas = collect($a['brechas'])->keyBy(fn ($b) => $b['dimension'].':'.($b['elemento_id'] ?? 0));

    expect($brechas)->toHaveCount(3)
        ->and($brechas['Competencia:1'])->toMatchArray(['diferencia' => -1, 'tiempo_cierre_meses' => 6, 'curso' => ['disponible' => true, 'ruta' => '/formacion?competencia=1']])
        ->and($brechas['Competencia:2']['curso'])->toBeNull() // sin oferta formativa
        ->and($brechas['Experiencia:0'])->toMatchArray(['tiempo_cierre_meses' => 18, 'curso' => null])
        ->and($a['preparacion'])->toBe('NoLista')
        ->and($a['cumplimiento_por_dimension']['Experiencia'])->toBe(0.6)
        ->and($a['resultados'][0])->not->toHaveKey('cumplimiento');
});

it('nunca incluye puntajes, rankings, quién la generó ni datos de otras personas', function () {
    $yo = actuarComoColaboradorDeAdecuacion();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['colaborador' => $yo, 'actor' => 77]);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11]);

    $crudo = $this->getJson('/api/adecuaciones/mi-adecuacion')->assertOk()->getContent();

    foreach (['puntaje', 'ranking', 'posicion', 'created_by', 'generada_por', 'creado_por', 'colaborador_id', '"colaborador"', 'Luis Mora'] as $prohibido) {
        expect($crudo)->not->toContain($prohibido);
    }
});

it('no acepta un identificador de colaborador: siempre usa la sesión', function () {
    $yo = actuarComoColaboradorDeAdecuacion();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11]);

    $d = $this->getJson('/api/adecuaciones/mi-adecuacion?colaborador_id=11')->assertOk()->json('data');

    expect($d['adecuaciones'])->toBe([]);
    unset($yo);
});

it('el colaborador no puede leer el historial ni el detalle de gestión', function () {
    actuarComoColaboradorDeAdecuacion();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11]);

    $this->getJson('/api/adecuaciones')->assertForbidden();
    $this->getJson("/api/adecuaciones/{$a->id}")->assertForbidden();
    $this->getJson('/api/adecuaciones/mapa')->assertForbidden();
});

it('el administrador no usa «Mi adecuación» y un usuario sin sesión recibe 401', function () {
    actuarComoAdminDeTenant();
    $this->getJson('/api/adecuaciones/mi-adecuacion')->assertForbidden();
});

it('sin sesión recibe 401', function () {
    $this->getJson('/api/adecuaciones/mi-adecuacion')->assertUnauthorized();
});

it('una cuenta sin ficha asociada recibe una lista vacía', function () {
    actuarComoUsuarioDeTenant();
    adecuacionFalsos();

    $this->getJson('/api/adecuaciones/mi-adecuacion')->assertOk()->assertJsonPath('data.adecuaciones', []);
});
