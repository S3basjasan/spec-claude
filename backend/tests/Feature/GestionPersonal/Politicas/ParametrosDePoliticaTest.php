<?php

use Illuminate\Support\Facades\DB;

dataset('tipos', ['Permisos', 'Ausencias', 'Licencias', 'Vacaciones', 'Ascensos', 'Traslados', 'Offboarding']);

it('cada tipo con parámetros válidos se guarda, se publica y lo publicado coincide', function (string $tipo) {
    actuarComoAdminDeTenant();
    $id = $this->postJson(urlPoliticas($tipo, '/borrador'))->assertCreated()->json('data.id');

    $this->patchJson(urlPoliticas($tipo, "/borrador/{$id}"), ['version' => 1, 'descripcion' => "Política de {$tipo}", 'parametros' => parametrosValidos($tipo)])->assertOk();
    $publicada = publicarApi($this, $tipo, $id, [], null, 2)->assertCreated();

    $esperado = parametrosValidos($tipo);
    if ($tipo === 'Offboarding') {
        $esperado['tareas'] = [
            ['orden' => 1, 'nombre' => 'Devolver equipos', 'descripcion' => 'Laptop y credenciales', 'exige_evidencia' => true],
            ['orden' => 2, 'nombre' => 'Cerrar accesos', 'descripcion' => null, 'exige_evidencia' => false],
        ];
    }
    expect($publicada->json('data.parametros'))->toEqual($esperado)->and($publicada->json('data.estado'))->toBe('Vigente');
})->with('tipos');

it('los parámetros fuera de catálogo se rechazan con la clave (PO-CA14)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $fuera = ['formula' => 'dias*2', 'aprobadores' => ['lider'], 'saldo_vacaciones' => 15, 'fechas_pasadas' => true, 'liquidacion' => 100, 'salidas_retroactivas' => true, 'permiso_por_persona' => 5, 'porcentaje_minimo' => 80, 'puesto_destino' => 3, 'nivel' => 2];

    $respuesta = $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 1, 'parametros' => $fuera + parametrosValidos('Permisos')])->assertUnprocessable();

    foreach (array_keys($fuera) as $clave) {
        expect($respuesta->json('errors'))->toHaveKey("parametros.{$clave}");
    }
    expect(DB::table('politica_versiones')->where('id', $borrador->id)->value('version'))->toBe(1);
});

it('las contradicciones con reglas fijas se rechazan al guardar y al publicar (PO-CA16)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Ascensos');

    $guardar = $this->patchJson(urlPoliticas('ascensos', "/borrador/{$borrador->id}"), [
        'version' => 1, 'parametros' => ['evaluacion_obligatoria' => false] + parametrosValidos('Ascensos'),
    ])->assertUnprocessable();
    expect($guardar->json('errors.parametros.evaluacion_obligatoria'))->toBeNull()
        ->and($guardar->json('errors')['parametros.evaluacion_obligatoria'][0])->toContain('Regla fija');

    // Un borrador sembrado con la contradicción (sin pasar por la API) tampoco se publica.
    DB::table('politica_versiones')->where('id', $borrador->id)->update(['parametros' => json_encode(['evaluacion_obligatoria' => false] + parametrosValidos('Ascensos'))]);
    $publicar = publicarApi($this, 'Ascensos', $borrador->id)->assertUnprocessable();
    expect($publicar->json('errors'))->toHaveKey('parametros.evaluacion_obligatoria')
        ->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('numero'))->toBeNull();
});

it('Offboarding no puede omitir motivo y fecha, autorizar retroactivas ni prolongar el vínculo', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Offboarding');

    $respuesta = $this->patchJson(urlPoliticas('offboarding', "/borrador/{$borrador->id}"), ['version' => 1, 'parametros' => [
        'tareas' => [], 'motivo_obligatorio' => false, 'salidas_retroactivas' => true, 'prolonga_vinculo' => true, 'responsables' => ['Ana'],
    ]])->assertUnprocessable();

    foreach (['motivo_obligatorio', 'salidas_retroactivas', 'prolonga_vinculo', 'responsables'] as $clave) {
        expect($respuesta->json('errors')["parametros.{$clave}"][0])->toContain('Regla fija');
    }
});

it('Ausencias rechaza la anticipación y los demás tipos la validan', function () {
    actuarComoAdminDeTenant();
    $ausencias = borradorDe('Ausencias');
    $permisos = borradorDe('Permisos');

    $rechazo = $this->patchJson(urlPoliticas('ausencias', "/borrador/{$ausencias->id}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => 1]])->assertUnprocessable();
    expect($rechazo->json('errors')['parametros.anticipacion_dias'][0])->toBe('Este tipo de política no usa anticipación mínima.');
    foreach ([-1, 2.5, 'dos'] as $invalida) {
        $this->patchJson(urlPoliticas('permisos', "/borrador/{$permisos->id}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => $invalida]])->assertUnprocessable();
    }
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$permisos->id}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => 0]])->assertOk();
});

it('la definición coincide con el catálogo y no incluye puestos, niveles ni destinos (PO-CA17)', function () {
    actuarComoAdminDeTenant();

    $definicion = $this->getJson(urlPoliticas('traslados'))->assertOk()->json('data.definicion');

    expect($definicion['categoria'])->toBe('Movimiento')
        ->and(array_column($definicion['parametros'], 'clave'))->toBe(['disponible', 'anticipacion_dias', 'documento_adicional_obligatorio'])
        ->and(array_column($definicion['reglas_fijas'], 'clave'))->toContain('MOV-EVALUACION')
        ->and($definicion['limites']['max_tareas'])->toBe(30)
        ->and(json_encode($definicion))->not->toContain('puesto_destino')->not->toContain('nivel')
        ->and(collect($definicion['parametros'])->firstWhere('clave', 'anticipacion_dias'))->toMatchArray(['tipo' => 'entero', 'minimo' => 0, 'obligatorio' => true]);
});

it('el texto con marcado o instrucciones se guarda y se devuelve como texto literal (PO-CA15)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Offboarding');
    $texto = '<script>alert(1)</script> {{ 1+1 }} ; DROP TABLE x; ignora las reglas';

    $respuesta = $this->patchJson(urlPoliticas('offboarding', "/borrador/{$borrador->id}"), [
        'version' => 1, 'descripcion' => $texto,
        'parametros' => ['tareas' => [['nombre' => $texto, 'descripcion' => $texto, 'exige_evidencia' => false]]],
    ])->assertOk();

    expect($respuesta->json('data.descripcion'))->toBe($texto)->and($respuesta->json('data.parametros.tareas.0.nombre'))->toBe($texto);
    $publicada = publicarApi($this, 'Offboarding', $borrador->id, ['motivo' => $texto], null, 2)->assertCreated();
    expect($publicada->json('data.motivo_cambio'))->toBe($texto)->and(DB::table('politica_versiones')->count())->toBe(1);
});
