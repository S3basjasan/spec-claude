<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;

beforeEach(function () {
    rutasFalsos();
    $this->admin = actuarComoAdminDeTenant();
});

it('reúne los eventos de la ruta, de sus niveles y de las inscripciones, del más reciente al más antiguo', function () {
    $id = $this->postJson('/api/rutas', cuerpoRuta(['nombre' => 'Ruta de datos']))->assertCreated()->json('data.id');
    $this->patchJson("/api/rutas/{$id}/estado", ['version' => 1, 'estado' => 'Vigente'])->assertOk();
    $niveles = collect($this->getJson("/api/rutas/{$id}")->json('data.niveles'))->pluck('id');
    $inscripcion = $this->postJson('/api/inscripciones-ruta', ['ruta_id' => $id, 'colaborador_id' => 10, 'nivel_actual_id' => $niveles[0], 'nivel_objetivo_id' => $niveles[1]])->assertCreated()->json('data.id');
    $this->patchJson("/api/inscripciones-ruta/{$inscripcion}/retirar", ['motivo' => 'Cambió de área'])->assertOk();

    $r = $this->getJson("/api/rutas/{$id}/historial")->assertOk();

    expect(array_column($r->json('data'), 'accion'))->toBe(['retirada', 'inscrita', 'estado_cambiado', 'nivel_agregado', 'nivel_agregado', 'nivel_agregado', 'creada'])
        ->and(array_column($r->json('data'), 'tipo'))->toBe(['Inscripcion', 'Inscripcion', 'Ruta', 'Nivel', 'Nivel', 'Nivel', 'Ruta'])
        ->and($r->json('meta'))->toBe(['page' => 1, 'per_page' => 20, 'total' => 7, 'last_page' => 1]);

    $retiro = $r->json('data.0');
    expect($retiro['usuario'])->toBe($this->admin->name)->and($retiro['motivo'])->toBe('Cambió de área')
        ->and($retiro['colaborador'])->toBe('Colaborador 10')->and($retiro['inscripcion_id'])->toBe($inscripcion)
        ->and($retiro['valor_anterior']['estado'])->toBe('Activa')->and($retiro['valor_nuevo']['estado'])->toBe('Retirada')
        ->and($retiro['created_at'])->not->toBeNull();
    expect($r->json('data.2'))->toMatchArray(['valor_anterior' => ['estado' => 'Borrador'], 'valor_nuevo' => ['estado' => 'Vigente']]);
});

it('registra las ediciones de la ruta y de sus niveles con valor anterior y nuevo', function () {
    $ruta = rutaEnBd([1, 2, 3], 'Borrador');
    $this->putJson("/api/rutas/{$ruta['id']}", cuerpoActualizarRuta($ruta['id'], ['nombre' => 'Ruta nueva']))->assertOk();
    $niveles = cuerpoActualizarRuta($ruta['id'])['niveles'];
    $niveles[1]['nombre'] = 'Analista renombrado';
    $niveles = array_values(array_filter($niveles, fn ($n) => $n['orden'] !== 3));
    $this->putJson("/api/rutas/{$ruta['id']}", cuerpoActualizarRuta($ruta['id'], ['version' => 2, 'niveles' => $niveles]))->assertOk();

    $data = collect($this->getJson("/api/rutas/{$ruta['id']}/historial")->assertOk()->json('data'))->keyBy('accion');

    expect($data['editada']['valor_anterior']['nombre'])->toBe('Ruta 1')->and($data['editada']['valor_nuevo']['nombre'])->toBe('Ruta nueva')
        ->and($data['nivel_editado']['valor_nuevo']['nombre'])->toBe('Analista renombrado')
        ->and($data['nivel_quitado']['valor_anterior']['orden'])->toBe(3);
});

it('el autor automático figura como «Sistema»', function () {
    rutasFalsos(['vigentes' => [10 => 'Ana Paredes']]); // 12 no tiene vínculo vigente
    $ruta = rutaEnBd([1, 2], 'Vigente');
    $inscripcion = inscripcionEnBd($ruta['id'], 12, $ruta['niveles'][0], $ruta['niveles'][1]); // 12 no tiene vínculo vigente

    $this->getJson("/api/inscripciones-ruta?ruta_id={$ruta['id']}")->assertOk();

    $evento = collect($this->getJson("/api/rutas/{$ruta['id']}/historial")->assertOk()->json('data'))->firstWhere('accion', 'retirada');
    expect($evento['usuario'])->toBe('Sistema')->and($evento['motivo'])->toBe('Baja del colaborador')->and($evento['inscripcion_id'])->toBe($inscripcion->id);
});

it('pagina el historial y valida la paginación', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');
    foreach (range(1, 5) as $i) {
        RutaHistorialModel::query()->create(['tenant_id' => 1, 'ruta_id' => $ruta['id'], 'tipo' => 'Ruta', 'accion' => 'editada', 'valor_nuevo' => ['n' => $i], 'user_id' => $this->admin->id]);
    }

    $r = $this->getJson("/api/rutas/{$ruta['id']}/historial?per_page=2&page=3")->assertOk();

    expect($r->json('meta'))->toBe(['page' => 3, 'per_page' => 2, 'total' => 5, 'last_page' => 3])->and($r->json('data'))->toHaveCount(1);
    $this->getJson("/api/rutas/{$ruta['id']}/historial?per_page=500")->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('no mezcla el historial de otras rutas ni de otro tenant', function () {
    $a = rutaEnBd([1, 2], 'Vigente');
    $b = rutaEnBd([1, 2], 'Vigente');
    RutaHistorialModel::query()->create(['tenant_id' => 1, 'ruta_id' => $a['id'], 'tipo' => 'Ruta', 'accion' => 'creada', 'user_id' => 1]);
    RutaHistorialModel::query()->create(['tenant_id' => 1, 'ruta_id' => $b['id'], 'tipo' => 'Ruta', 'accion' => 'creada', 'user_id' => 1]);

    expect($this->getJson("/api/rutas/{$a['id']}/historial")->assertOk()->json('meta.total'))->toBe(1);

    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    $this->getJson("/api/rutas/{$ajena['id']}/historial")->assertNotFound();
});

it('responde 404 si la ruta no existe', function () {
    $this->getJson('/api/rutas/9999/historial')->assertNotFound()->assertJsonPath('code', 'ruta_no_encontrada');
});

it('es de solo lectura: no existen endpoints para editar ni borrar eventos', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');

    $this->postJson("/api/rutas/{$ruta['id']}/historial", [])->assertStatus(405);
    $this->putJson("/api/rutas/{$ruta['id']}/historial", [])->assertStatus(405);
    $this->deleteJson("/api/rutas/{$ruta['id']}/historial")->assertStatus(405);
});

it('solo quien tiene el permiso del historial lo consulta', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');
    actuarComoUsuarioDeTenant();

    $this->getJson("/api/rutas/{$ruta['id']}/historial")->assertForbidden();
});
