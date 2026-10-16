<?php

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;

/** Departamento 5 con la subunidad 6; los colaboradores 10 y 11 del 5, el 12 de la subunidad 6 y el 13 de otra unidad. */
function departamentoConSubunidad(array $extra = []): array
{
    return dnfFalsos($extra + [
        'evaluables' => [evaluableDe(10, 1, 5), evaluableDe(11, 2, 5), evaluableDe(12, 1, 6), evaluableDe(13, 1, 9)],
        'subunidades' => [5 => [6]],
        'excluidos' => [
            ['colaborador_id' => 20, 'nombre' => 'Sin puesto', 'motivo' => 'SinPuesto'],
            ['colaborador_id' => 21, 'nombre' => 'Sin vínculo', 'motivo' => 'SinVinculoVigente'],
        ],
        'perfiles' => [
            1 => perfilDePuesto(1, [competenciaDePerfil(1, 'A'), competenciaDePerfil(2, 'B', 'Especifica'), competenciaDePerfil(5, 'C', 'Cardinal', false)]),
            2 => perfilDePuesto(2, [competenciaDePerfil(1, 'B'), competenciaDePerfil(6, 'A', 'Cardinal', false)]),
        ],
        'niveles' => [10 => [1 => 'C', 2 => 'B'], 11 => [1 => 'B'], 12 => [1 => 'D']],
    ]);
}

it('genera un resultado por colaborador evaluable del departamento y de sus subunidades', function () {
    departamentoConSubunidad();
    actuarComoAdminDeTenant();

    $respuesta = $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertCreated();

    $datos = $respuesta->json('data');
    expect($datos['alcance'])->toBe('Departamento')
        ->and($datos['departamento'])->toBe(['id' => 5, 'nombre' => 'Unidad 5'])
        ->and(array_column($datos['colaboradores'], 'colaborador_id'))->toBe([10, 11, 12])
        ->and($datos['resumen']['colaboradores'])->toBe(3)
        ->and($datos['resumen']['cantidad_brechas'])->toBe(2) // 10: C vs A; 12: D vs A (11 cumple)
        ->and($datos['resumen']['prioridad_mas_alta'])->toBe('Critica');
    // La respuesta de una masiva no incluye las brechas de cada colaborador (se piden con GET /{id}?colaborador_id=).
    expect($datos['colaboradores'][0])->not->toHaveKey('brechas')
        ->and($datos['colaboradores'][0]['puesto']['nombre'])->toBe('Puesto 1')
        ->and($datos['colaboradores'][0]['cantidad_brechas'])->toBe(1);
});

it('excluye con su motivo a quienes no se pueden evaluar y lo informa en los avisos', function () {
    departamentoConSubunidad();
    actuarComoAdminDeTenant();

    $avisos = $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertCreated()->json('avisos');

    expect($avisos['excluidos'])->toBe([
        ['colaborador_id' => 20, 'nombre' => 'Sin puesto', 'motivo' => 'SinPuesto'],
        ['colaborador_id' => 21, 'nombre' => 'Sin vínculo', 'motivo' => 'SinVinculoVigente'],
    ]);
});

it('excluye por falta de perfil al colaborador cuyo puesto no tiene competencias activas', function () {
    departamentoConSubunidad(['perfiles' => [
        1 => perfilDePuesto(1, [competenciaDePerfil(1, 'A')]),
        2 => perfilDePuesto(2, [competenciaDePerfil(6, 'A', 'Cardinal', false)]),
    ]]);
    actuarComoAdminDeTenant();

    $respuesta = $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertCreated();

    expect(array_column($respuesta->json('data.colaboradores'), 'colaborador_id'))->toBe([10, 12])
        ->and(collect($respuesta->json('avisos.excluidos'))->firstWhere('colaborador_id', 11)['motivo'])->toBe('SinPerfil');
});

it('cuenta las competencias inactivas omitidas sin repetir las del mismo puesto', function () {
    departamentoConSubunidad();
    actuarComoAdminDeTenant();

    $respuesta = $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertCreated();

    // Inactivas distintas: la 5 (puesto 1, dos colaboradores) y la 6 (puesto 2).
    expect($respuesta->json('data.competencias_omitidas'))->toBe(2)
        ->and($respuesta->json('avisos.competencias_omitidas'))->toBe(2);
});

it('consulta el perfil una vez por puesto distinto y los niveles una sola vez por lote', function () {
    $dobles = departamentoConSubunidad();
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertCreated();

    expect($dobles['perfiles']->consultas)->toBe([1 => 1, 2 => 1])
        ->and($dobles['niveles']->consultas)->toBe(1);
});

it('no deja nada guardado si ningún colaborador es evaluable', function () {
    dnfFalsos(['evaluables' => [], 'excluidos' => [['colaborador_id' => 20, 'nombre' => 'Sin puesto', 'motivo' => 'SinPuesto']]]);
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No hay colaboradores evaluables.');
    expect(DnfModel::query()->count())->toBe(0);
});

it('no genera nada si todos los evaluables carecen de perfil', function () {
    dnfFalsos(['evaluables' => [evaluableDe(10, 1, 5)], 'perfiles' => []]);
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertUnprocessable();
    expect(DnfModel::query()->count())->toBe(0);
});

it('rechaza un departamento que no existe', function () {
    app()->instance(
        NombresDeReferenciaInterface::class,
        new class implements NombresDeReferenciaInterface
        {
            public function colaboradores(int $tenantId, array $ids): array
            {
                return [];
            }

            public function puestos(array $ids): array
            {
                return [];
            }

            public function competencias(array $ids): array
            {
                return [];
            }

            public function unidades(array $ids): array
            {
                return [];
            }
        },
    );
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 99])
        ->assertUnprocessable()
        ->assertJsonPath('errors.departamento_id.0', 'El departamento no existe.');
});

it('genera una masiva de 100 colaboradores en menos de 60 segundos (SC-002)', function () {
    $evaluables = [];
    $niveles = [];
    foreach (range(1, 100) as $i) {
        $evaluables[] = evaluableDe($i, 1, 5);
        $niveles[$i] = array_fill_keys(range(1, 30), ['A', 'B', 'C', 'D'][$i % 4]);
    }
    dnfFalsos([
        'evaluables' => $evaluables,
        'niveles' => $niveles,
        'perfiles' => [1 => perfilDePuesto(1, array_map(fn (int $c) => competenciaDePerfil($c, 'A'), range(1, 30)))],
    ]);
    actuarComoAdminDeTenant();
    $inicio = microtime(true);

    $respuesta = $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 5])->assertCreated();

    expect(microtime(true) - $inicio)->toBeLessThan(60.0)
        ->and($respuesta->json('data.resumen.colaboradores'))->toBe(100);
});
