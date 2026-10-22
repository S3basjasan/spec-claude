<?php

use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;

/** Cuerpo para guardar el escenario base: la única brecha evaluada es la competencia 1 (C frente a B). */
function cuerpoGenerar(int $inscripcionId, array $cambios = []): array
{
    return $cambios + [
        'colaborador_id' => 10,
        'inscripcion_id' => $inscripcionId,
        'tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 6]],
    ];
}

it('guarda la adecuación con código, puesto actual, umbral y usuario, y la devuelve con 201', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $r = $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id, ['observaciones' => '  Candidato prometedor  ']))
        ->assertCreated()->assertHeader('Location');
    $d = $r->json('data');

    expect($d['codigo'])->toBe('ADE-0001')
        ->and($d['puesto_actual']['id'])->toBe(1)
        ->and($d['puesto_objetivo']['id'])->toBe(2)
        ->and($d['umbral_meses'])->toBe(12)
        ->and($d['observaciones'])->toBe('Candidato prometedor')
        ->and($d['preparacion'])->toBe('Incompleta') // la competencia 3 no tiene valor
        ->and($d['es_vigente'])->toBeTrue()
        ->and($d['brechas'])->toBe(1)
        ->and($d['tiempo_maximo_cierre_meses'])->toBe(6)
        ->and($d['acciones']['crear_plan']['habilitado'])->toBeFalse();

    $modelo = AdecuacionModel::query()->firstOrFail();
    expect($modelo->inscripcion_id)->toBe($inscripcion->id)
        ->and($modelo->nivel_objetivo_id)->not->toBeNull()
        ->and((float) $modelo->experiencia_requerida_anios)->toBe(1.0)
        ->and(AdecuacionDetalleModel::query()->where('adecuacion_id', $modelo->id)->count())->toBe(4);
});

it('dos adecuaciones seguidas reciben códigos distintos y la segunda es la vigente', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $a = $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id))->assertCreated()->json('data');
    $b = $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id))->assertCreated()->json('data');

    expect([$a['codigo'], $b['codigo']])->toBe(['ADE-0001', 'ADE-0002']);
});

it('exige el tiempo de cierre de cada brecha', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones', ['colaborador_id' => 10, 'inscripcion_id' => $inscripcion->id])
        ->assertStatus(422)->assertJsonPath('code', 'tiempo_cierre_requerido')
        ->assertJsonStructure(['errors' => ['tiempos_cierre.Competencia:1']]);
    expect(AdecuacionModel::query()->count())->toBe(0);
});

it('rechaza tiempos fuera de 1 a 60 meses o no enteros', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    foreach ([0, 61, -3, 2.5, 'seis'] as $meses) {
        $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id, ['tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => $meses]]]))
            ->assertStatus(422);
    }
    expect(AdecuacionModel::query()->count())->toBe(0);
});

it('rechaza un tiempo de cierre para un requisito que no es brecha o que no existe', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $noBrecha = cuerpoGenerar($inscripcion->id, ['tiempos_cierre' => [
        ['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 6],
        ['dimension' => 'Competencia', 'elemento_id' => 2, 'meses' => 3],
    ]]);
    $this->postJson('/api/adecuaciones', $noBrecha)->assertStatus(422)->assertJsonPath('code', 'tiempo_cierre_requerido');

    $inexistente = cuerpoGenerar($inscripcion->id, ['tiempos_cierre' => [
        ['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 6],
        ['dimension' => 'Competencia', 'elemento_id' => 77, 'meses' => 3],
    ]]);
    $this->postJson('/api/adecuaciones', $inexistente)->assertStatus(422)->assertJsonPath('code', 'tiempo_cierre_requerido');
});

it('el servidor recalcula y no acepta valores, estados ni preparación del cliente', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id, [
        'preparacion' => 'Lista',
        'umbral_meses' => 99,
        'resultados' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'valor' => 'A', 'estado' => 'Supera', 'diferencia' => 2]],
    ]))->assertCreated()->json('data');

    $r = collect($d['resultados'])->firstWhere('elemento_id', 1);
    expect($d['preparacion'])->toBe('Incompleta')->and($d['umbral_meses'])->toBe(12)
        ->and($r)->toMatchArray(['valor' => 'C', 'estado' => 'ConBrecha', 'diferencia' => -1]);
});

it('clasifica la preparación en las cuatro categorías', function () {
    // Lista: todo cumple o supera.
    [, $i1] = escenarioDeAdecuacion(['niveles' => [10 => [1 => 'B', 2 => 'C', 3 => 'C']]]);
    $lista = $this->postJson('/api/adecuaciones', ['colaborador_id' => 10, 'inscripcion_id' => $i1->id])->assertCreated()->json('data');
    expect($lista['preparacion'])->toBe('Lista');

    // En desarrollo: una brecha de 12 meses (límite del umbral).
    adecuacionFalsos(['niveles' => [10 => [1 => 'C', 2 => 'C', 3 => 'C']]]);
    $enDesarrollo = $this->postJson('/api/adecuaciones', cuerpoGenerar($i1->id, ['tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 12]]]))->assertCreated()->json('data');
    expect($enDesarrollo['preparacion'])->toBe('EnDesarrollo');

    // No lista: una brecha por encima del umbral.
    $noLista = $this->postJson('/api/adecuaciones', cuerpoGenerar($i1->id, ['tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 13]]]))->assertCreated()->json('data');
    expect($noLista['preparacion'])->toBe('NoLista');

    // Incompleta: la competencia 3 no tiene valor.
    adecuacionFalsos(['niveles' => [10 => [1 => 'C', 2 => 'C']]]);
    $incompleta = $this->postJson('/api/adecuaciones', cuerpoGenerar($i1->id))->assertCreated()->json('data');
    expect($incompleta['preparacion'])->toBe('Incompleta');
});

it('responde 409 si el puesto objetivo recalculado ya no coincide con el que vio el administrador', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id, ['puesto_objetivo_esperado_id' => 3]))
        ->assertStatus(409)->assertJsonPath('code', 'objetivo_cambio');
    expect(AdecuacionModel::query()->count())->toBe(0);

    $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id, ['puesto_objetivo_esperado_id' => 2]))->assertCreated();
});

it('responde 503 y no guarda si una fuente imprescindible no responde', function () {
    [, $inscripcion] = escenarioDeAdecuacion();
    app()->instance(PerfilesDePuestoInterface::class, new class implements PerfilesDePuestoInterface
    {
        public function perfilDe(int $puestoId): ?array
        {
            throw new RuntimeException('Estructura caída');
        }
    });

    $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id))->assertStatus(503)->assertJsonPath('code', 'fuente_no_disponible');
    expect(AdecuacionModel::query()->count())->toBe(0);
});

it('valida las observaciones y los datos del objetivo', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id, ['observaciones' => str_repeat('a', 501)]))
        ->assertStatus(422)->assertJsonValidationErrors('observaciones');
    $this->postJson('/api/adecuaciones', ['colaborador_id' => 10])->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido');
    $this->postJson('/api/adecuaciones', ['colaborador_id' => 99, 'puesto_objetivo_id' => 2])->assertStatus(422)->assertJsonPath('code', 'colaborador_no_elegible');
});

it('las adecuaciones no se pueden editar ni eliminar por la API', function () {
    [, $inscripcion] = escenarioDeAdecuacion();
    $id = $this->postJson('/api/adecuaciones', cuerpoGenerar($inscripcion->id))->assertCreated()->json('data.id');

    foreach (['putJson', 'patchJson', 'deleteJson'] as $metodo) {
        expect($this->{$metodo}("/api/adecuaciones/{$id}", ['observaciones' => 'x'])->getStatusCode())->toBeIn([404, 405]);
    }
    expect(AdecuacionModel::query()->count())->toBe(1);
});

it('un puesto directo guarda sin inscripción y con la experiencia indicada', function () {
    escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones', [
        'colaborador_id' => 10, 'puesto_objetivo_id' => 3, 'experiencia_requerida_anios' => 5,
        'tiempos_cierre' => [
            ['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 10],
            ['dimension' => 'Competencia', 'elemento_id' => 3, 'meses' => 4],
            ['dimension' => 'Experiencia', 'meses' => 24],
        ],
    ]);

    // La competencia 3 no tiene valor (Sin evaluación) y no admite tiempo: se rechaza.
    $d->assertStatus(422)->assertJsonPath('code', 'tiempo_cierre_requerido');

    $ok = $this->postJson('/api/adecuaciones', [
        'colaborador_id' => 10, 'puesto_objetivo_id' => 3, 'experiencia_requerida_anios' => 5,
        'tiempos_cierre' => [
            ['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 10],
            ['dimension' => 'Experiencia', 'meses' => 24],
        ],
    ])->assertCreated()->json('data');

    expect($ok['inscripcion'])->toBeNull()->and($ok['experiencia_requerida_anios'])->toEqual(5.0)
        ->and($ok['preparacion'])->toBe('Incompleta')
        ->and($ok['tiempo_maximo_cierre_meses'])->toBe(24);
});
