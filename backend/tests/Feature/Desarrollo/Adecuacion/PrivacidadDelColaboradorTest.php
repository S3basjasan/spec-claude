<?php

/** SC-004: el colaborador nunca ve puntajes de comparación ni datos de otras personas, por ningún camino. */
it('el colaborador recibe 403 en todos los endpoints de gestión, incluidos los que exponen puntajes', function () {
    actuarComoColaboradorDeAdecuacion();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11]);

    foreach ([
        '/api/adecuaciones',
        '/api/adecuaciones/1',
        '/api/adecuaciones/mapa',
        '/api/adecuaciones/comparar?puesto_objetivo_id=2&colaborador_ids[]=10&colaborador_ids[]=11',
        '/api/adecuaciones/parametros',
        '/api/adecuaciones/opciones',
    ] as $url) {
        $this->getJson($url)->assertForbidden();
    }
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 11, 'puesto_objetivo_id' => 2])->assertForbidden();
    $this->postJson('/api/adecuaciones', ['colaborador_id' => 11, 'puesto_objetivo_id' => 2])->assertForbidden();
    $this->putJson('/api/adecuaciones/parametros', ['pesos' => ['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30], 'umbral_meses' => 12])->assertForbidden();
});

it('«Mi adecuación» solo devuelve datos del propio colaborador aunque existan muchas adecuaciones ajenas', function () {
    $yo = actuarComoColaboradorDeAdecuacion();
    foreach ([11, 12, 13] as $otro) {
        adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 5]], ['colaborador' => $otro, 'puesto' => 2]);
    }
    $mia = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => $yo, 'puesto' => 3]);

    $d = $this->getJson('/api/adecuaciones/mi-adecuacion')->assertOk()->json('data');

    expect(array_column($d['adecuaciones'], 'id'))->toBe([$mia->id])
        ->and(json_encode($d))->not->toContain('Colaborador 11')->not->toContain('Colaborador 12')->not->toContain('puntaje');
});

it('el administrador no recibe datos de «Mi adecuación» (no tiene ver_propio)', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();

    $this->getJson('/api/adecuaciones/mi-adecuacion')->assertForbidden();
});
