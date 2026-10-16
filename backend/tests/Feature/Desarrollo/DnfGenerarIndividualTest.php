<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;

function perfilDeAna(): array
{
    return perfilDePuesto(1, [
        competenciaDePerfil(1, 'A', 'Cardinal'),
        competenciaDePerfil(2, 'B', 'Especifica'),
        competenciaDePerfil(3, 'C', 'Cardinal'),
        competenciaDePerfil(4, 'B', 'Cardinal'),
        competenciaDePerfil(5, 'A', 'Cardinal', false),
    ]);
}

function dobleDeAna(array $extra = []): array
{
    return dnfFalsos($extra + [
        'evaluables' => [evaluableDe(10, 1)],
        'perfiles' => [1 => perfilDeAna()],
        'niveles' => [10 => [1 => 'C', 2 => 'D', 4 => 'B']],
    ]);
}

it('genera una DNF individual con los valores calculados y avisos', function () {
    dobleDeAna();
    $admin = actuarComoAdminDeTenant();
    $inicio = microtime(true);

    $respuesta = $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->assertCreated();

    expect(microtime(true) - $inicio)->toBeLessThan(10.0); // SC-001
    $respuesta->assertHeader('Location');
    $datos = $respuesta->json('data');
    expect($datos['codigo'])->toBe('DNF-0001')
        ->and($datos['alcance'])->toBe('Individual')
        ->and($datos['departamento'])->toBeNull()
        ->and($datos['generado_por']['id'])->toBe($admin->id)
        ->and($datos['competencias_omitidas'])->toBe(1)
        ->and($datos['plan_formacion'])->toBeNull()
        ->and($datos['resumen'])->toBe(['colaboradores' => 1, 'cantidad_brechas' => 2, 'prioridad_mas_alta' => 'Alta', 'sin_evaluacion' => 1])
        ->and($datos['fecha'])->not->toBeEmpty();

    $resultado = $datos['colaboradores'][0];
    expect($resultado['colaborador_id'])->toBe(10)
        ->and($resultado['puesto']['codigo'])->toBe('PU-001')
        ->and($resultado['brechas'])->toHaveCount(4);

    $porId = collect($resultado['brechas'])->keyBy('competencia_id');
    // C vs A: -2, 50 %, Alta
    expect($porId[1])->toMatchArray(['nivel_requerido' => 'A', 'nivel_actual' => 'C', 'diferencia' => -2, 'brecha_porcentaje' => 50, 'prioridad' => 'Alta', 'estado' => 'ConBrecha'])
        // D vs B (específica): -2 grados, Alta
        ->and($porId[2])->toMatchArray(['diferencia' => -2, 'prioridad' => 'Alta', 'estado' => 'ConBrecha'])
        // sin nivel actual: sin evaluación y sin cálculo
        ->and($porId[3])->toMatchArray(['nivel_actual' => null, 'diferencia' => null, 'brecha_porcentaje' => null, 'prioridad' => null, 'estado' => 'SinEvaluacion'])
        // cumple: 0 % y sin prioridad
        ->and($porId[4])->toMatchArray(['diferencia' => 0, 'brecha_porcentaje' => 0, 'prioridad' => null, 'estado' => 'Cumple'])
        ->and($porId)->not->toHaveKey(5);

    expect($respuesta->json('avisos'))->toBe(['competencias_omitidas' => 1, 'sin_evaluacion' => 1, 'excluidos' => []]);
});

it('ordena las brechas de mayor a menor prioridad', function () {
    dobleDeAna();
    actuarComoAdminDeTenant();

    $brechas = $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->json('data.colaboradores.0.brechas');

    expect(array_column($brechas, 'estado'))->toBe(['ConBrecha', 'ConBrecha', 'Cumple', 'SinEvaluacion']);
});

it('marca Supera cuando el nivel actual excede el requerido', function () {
    dnfFalsos([
        'evaluables' => [evaluableDe(10, 1)],
        'perfiles' => [1 => perfilDePuesto(1, [competenciaDePerfil(1, 'C')])],
        'niveles' => [10 => [1 => 'A']],
    ]);
    actuarComoAdminDeTenant();

    $brecha = $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->assertCreated()->json('data.colaboradores.0.brechas.0');

    expect($brecha)->toMatchArray(['diferencia' => 2, 'brecha_porcentaje' => 0, 'prioridad' => null, 'estado' => 'Supera']);
});

it('guarda los valores tal como se calcularon aunque cambien el perfil o los niveles', function () {
    $dobles = dobleDeAna();
    actuarComoAdminDeTenant();
    $id = $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->json('data.id');
    $antes = DnfDetalleModel::query()->orderBy('id')->get()->map->only(['competencia_id', 'nivel_requerido', 'nivel_actual', 'diferencia', 'prioridad'])->all();

    $dobles['niveles']->niveles = [10 => [1 => 'A', 2 => 'A', 3 => 'A', 4 => 'A']];
    $dobles['perfiles']->perfiles = [1 => perfilDePuesto(1, [competenciaDePerfil(1, 'D')])];

    $despues = DnfDetalleModel::query()->orderBy('id')->get()->map->only(['competencia_id', 'nivel_requerido', 'nivel_actual', 'diferencia', 'prioridad'])->all();
    expect($despues)->toBe($antes)
        ->and(DnfModel::query()->findOrFail($id)->competencias_omitidas)->toBe(1);
});

it('permite generar otra DNF del mismo colaborador sin advertencia', function () {
    dobleDeAna();
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->assertCreated();
    $segunda = $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->assertCreated();

    expect($segunda->json('data.codigo'))->toBe('DNF-0002')->and(DnfModel::query()->count())->toBe(2);
});

it('rechaza al colaborador no evaluable con el motivo', function (string $motivo, string $mensaje) {
    dnfFalsos(['excluidos' => [['colaborador_id' => 10, 'nombre' => 'Ana', 'motivo' => $motivo]]]);
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])
        ->assertUnprocessable()
        ->assertJsonPath('errors.colaborador_id.0', $mensaje);
    expect(DnfModel::query()->count())->toBe(0);
})->with([
    ['SinPuesto', 'El colaborador no tiene un puesto asignado.'],
    ['SinVinculoVigente', 'El colaborador no tiene un vínculo laboral vigente.'],
]);

it('rechaza al colaborador cuyo puesto no tiene perfil de competencias activas', function () {
    dnfFalsos([
        'evaluables' => [evaluableDe(10, 1)],
        'perfiles' => [1 => perfilDePuesto(1, [competenciaDePerfil(5, 'A', 'Cardinal', false)])],
    ]);
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])
        ->assertUnprocessable()
        ->assertJsonPath('errors.colaborador_id.0', 'El puesto del colaborador no tiene perfil de competencias.');
});

it('rechaza un colaborador que no existe en el tenant', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 999])
        ->assertUnprocessable()
        ->assertJsonPath('errors.colaborador_id.0', 'El colaborador no existe.');
});

it('responde "No hay colaboradores evaluables" si el puerto falla o no devuelve datos en una masiva', function () {
    dnfFalsos(['departamentos' => []]);
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', ['alcance' => 'Departamento', 'departamento_id' => 2])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No hay colaboradores evaluables.');
});

it('valida la solicitud', function (array $cuerpo, string $campo) {
    dnfFalsos();
    actuarComoAdminDeTenant();

    $this->postJson('/api/dnf', $cuerpo)->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    'sin alcance' => [[], 'alcance'],
    'alcance inválido' => [['alcance' => 'Otro'], 'alcance'],
    'individual sin colaborador' => [['alcance' => 'Individual'], 'colaborador_id'],
    'individual con departamento' => [['alcance' => 'Individual', 'colaborador_id' => 1, 'departamento_id' => 2], 'departamento_id'],
    'departamento sin departamento' => [['alcance' => 'Departamento'], 'departamento_id'],
    'departamento con colaborador' => [['alcance' => 'Departamento', 'departamento_id' => 2, 'colaborador_id' => 1], 'colaborador_id'],
    'colaborador no numérico' => [['alcance' => 'Individual', 'colaborador_id' => 'abc'], 'colaborador_id'],
]);

it('exige autenticación y rol de administrador', function () {
    dobleDeAna();

    $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->assertForbidden();
    expect(DnfModel::query()->count())->toBe(0);
});

it('no ofrece PUT, PATCH ni DELETE sobre la DNF (SC-005)', function (string $metodo, string $ruta) {
    actuarComoAdminDeTenant();

    $this->json($metodo, $ruta)->assertStatus(405);
})->with([
    ['PUT', '/api/dnf'],
    ['PATCH', '/api/dnf'],
    ['DELETE', '/api/dnf'],
]);

it('registra autor y fecha de generación', function () {
    dobleDeAna();
    $admin = actuarComoAdminDeTenant();

    $id = $this->postJson('/api/dnf', ['alcance' => 'Individual', 'colaborador_id' => 10])->json('data.id');

    $dnf = DnfModel::query()->findOrFail($id);
    expect((int) $dnf->created_by)->toBe($admin->id)->and($dnf->created_at)->not->toBeNull()->and((int) $dnf->tenant_id)->toBe(1);
});
