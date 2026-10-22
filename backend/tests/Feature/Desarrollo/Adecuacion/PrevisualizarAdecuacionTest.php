<?php

use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;

it('previsualiza el objetivo de la inscripción con la experiencia mínima del nivel y no guarda nada', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'inscripcion_id' => $inscripcion->id])
        ->assertOk()->json('data');

    expect($d['puesto_objetivo'])->toBe(['id' => 2, 'nombre' => 'Analista de datos'])
        ->and($d['puesto_actual']['id'])->toBe(1)
        ->and($d['tipo_cambio'])->toBe('Promocion')
        ->and($d['experiencia_requerida_anios'])->toEqual(1.0)
        ->and($d['umbral_meses'])->toBe(12)
        ->and($d['preparacion_provisoria'])->toBe('Incompleta')
        ->and($d['fuentes_no_disponibles'])->toBe([]);

    $porClave = collect($d['resultados'])->keyBy(fn ($r) => $r['dimension'].':'.($r['elemento_id'] ?? 0));
    expect($porClave['Competencia:1'])->toMatchArray(['requerido_actual' => 'C', 'requerido_objetivo' => 'B', 'valor' => 'C', 'diferencia' => -1, 'estado' => 'ConBrecha'])
        ->and($porClave['Competencia:2'])->toMatchArray(['requerido_objetivo' => 'C', 'valor' => 'C', 'diferencia' => 0, 'estado' => 'Cumple'])
        ->and($porClave['Competencia:3'])->toMatchArray(['requerido_actual' => null, 'requerido_objetivo' => 'C', 'valor' => null, 'diferencia' => null, 'estado' => 'SinEvaluacion'])
        ->and($porClave['Experiencia:0'])->toMatchArray(['valor_anios' => 3, 'requerido_objetivo_anios' => 1, 'diferencia' => 2, 'estado' => 'Supera']);
    expect(array_column($d['avisos'], 'tipo'))->toContain('sin_evaluacion', 'conocimientos_desactivados')
        ->not->toContain('experiencia_no_evaluada');
    expect(AdecuacionModel::query()->count())->toBe(0);
});

it('con puesto directo sin experiencia requerida la dimensión Experiencia no se evalúa', function () {
    escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 3])->assertOk()->json('data');

    expect($d['tipo_cambio'])->toBe('PuestoDirecto')->and($d['inscripcion_id'])->toBeNull()
        ->and(array_column($d['resultados'], 'dimension'))->not->toContain('Experiencia')
        ->and(array_column($d['avisos'], 'tipo'))->toContain('experiencia_no_evaluada')
        ->and($d['cumplimiento_por_dimension']['Experiencia'])->toBeNull();
});

it('con puesto directo y experiencia requerida evalúa la experiencia en años', function () {
    escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 3, 'experiencia_requerida_anios' => 5])
        ->assertOk()->json('data');

    $exp = collect($d['resultados'])->firstWhere('dimension', 'Experiencia');
    expect($exp)->toMatchArray(['requerido_objetivo_anios' => 5, 'valor_anios' => 3, 'diferencia' => -2, 'estado' => 'ConBrecha'])
        ->and($d['experiencia_requerida_anios'])->toEqual(5.0);
});

it('sin experiencia acumulada registrada, la experiencia requerida queda sin evaluación', function () {
    escenarioDeAdecuacion(['anios' => []]);

    $d = $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 2, 'experiencia_requerida_anios' => 2])
        ->assertOk()->json('data');

    expect(collect($d['resultados'])->firstWhere('dimension', 'Experiencia')['estado'])->toBe('SinEvaluacion')
        ->and($d['preparacion_provisoria'])->toBe('Incompleta');
});

it('omite las competencias inactivas del perfil y lo avisa', function () {
    escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 4])->assertOk()->json('data');

    expect(array_column($d['resultados'], 'elemento_id'))->toBe([1])
        ->and(collect($d['avisos'])->firstWhere('tipo', 'inactivos_omitidos')['cantidad'])->toBe(1);
});

it('avisa cuando el puesto objetivo es el puesto actual', function () {
    escenarioDeAdecuacion();

    $d = $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 1])->assertOk()->json('data');

    expect($d['tipo_cambio'])->toBe('MismoPuesto')->and(array_column($d['avisos'], 'tipo'))->toContain('mismo_puesto');
});

it('rechaza con 422 un objetivo no definido, ambiguo o inválido', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10])
        ->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido');
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'inscripcion_id' => $inscripcion->id, 'puesto_objetivo_id' => 2])
        ->assertStatus(422)->assertJsonPath('code', 'objetivo_ambiguo');
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 6])
        ->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido'); // puesto inactivo
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 99])
        ->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido'); // inexistente
});

it('rechaza un colaborador sin vínculo vigente o inexistente', function () {
    escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 99, 'puesto_objetivo_id' => 2])
        ->assertStatus(422)->assertJsonPath('code', 'colaborador_no_elegible');
});

it('rechaza una inscripción que no es Activa del colaborador', function () {
    [$ruta] = escenarioDeAdecuacion();
    $ajena = inscripcionEnBd($ruta['id'], 11, $ruta['niveles'][0], $ruta['niveles'][1]);
    $retirada = inscripcionEnBd($ruta['id'], 10, null, null, 'Retirada', motivo: 'Cambio');

    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'inscripcion_id' => $ajena->id])
        ->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido');
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'inscripcion_id' => $retirada->id])
        ->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido');
});

it('rechaza un puesto sin requisitos evaluables', function () {
    escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 5])
        ->assertStatus(422)->assertJsonPath('code', 'sin_requisitos_evaluables');
});

it('valida los campos de entrada', function () {
    escenarioDeAdecuacion();

    $this->postJson('/api/adecuaciones/previsualizar', [])->assertStatus(422)->assertJsonValidationErrors('colaborador_id');
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 2, 'experiencia_requerida_anios' => -1])
        ->assertStatus(422)->assertJsonValidationErrors('experiencia_requerida_anios');
    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 2, 'experiencia_requerida_anios' => 1.55])
        ->assertStatus(422)->assertJsonValidationErrors('experiencia_requerida_anios');
});

it('responde 503 si el perfil del puesto no responde', function () {
    escenarioDeAdecuacion();
    app()->instance(PerfilesDePuestoInterface::class, new class implements PerfilesDePuestoInterface
    {
        public function perfilDe(int $puestoId): ?array
        {
            throw new RuntimeException('Estructura caída');
        }
    });

    $this->postJson('/api/adecuaciones/previsualizar', ['colaborador_id' => 10, 'puesto_objetivo_id' => 2])
        ->assertStatus(503)->assertJsonPath('code', 'fuente_no_disponible');
});

it('lista en opciones los colaboradores vigentes y, para uno, sus inscripciones, puestos e historial', function () {
    [, $inscripcion] = escenarioDeAdecuacion();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']]);

    $lista = $this->getJson('/api/adecuaciones/opciones?q=Ana')->assertOk()->json('data');
    expect($lista['colaboradores'])->toBeArray();

    $d = $this->getJson('/api/adecuaciones/opciones?colaborador_id=10')->assertOk()->json('data');
    expect($d['inscripciones'])->toHaveCount(1)
        ->and($d['inscripciones'][0]['inscripcion_id'])->toBe($inscripcion->id)
        ->and($d['inscripciones'][0]['nivel_objetivo']['puesto_id'])->toBe(2)
        ->and(array_column($d['puestos'], 'id'))->toContain(1, 2, 3)->not->toContain(6)
        ->and($d['historial'])->toHaveCount(1)
        ->and($d['historial'][0]['codigo'])->toBe('ADE-0001');
});
