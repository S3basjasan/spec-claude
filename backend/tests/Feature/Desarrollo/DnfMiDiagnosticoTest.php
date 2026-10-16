<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;

/** Crea la ficha del colaborador y su cuenta; actúa como ese usuario y devuelve el id de la ficha. */
function actuarComoColaboradorConFicha(int $tenantId = 1): int
{
    $ficha = fichaDe([], $tenantId);
    $usuario = actuarComoUsuarioDeTenant($tenantId);
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => $tenantId, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id, 'puede_consultar_ficha' => true,
    ]);

    return (int) $ficha->id;
}

it('devuelve las brechas de la DNF más reciente con las prioritarias ordenadas', function () {
    dnfFalsos();
    $colaborador = actuarComoColaboradorConFicha();
    dnfDeEjemplo([$colaborador => [[1, 'A', 'D']]]); // DNF anterior: Crítica en 1
    dnfDeEjemplo([$colaborador => [
        [1, 'A', 'C'], // Alta, -2
        [2, 'B', 'C'], // Media, -1
        [3, 'A', 'D'], // Crítica, -3
        [4, 'B', 'B'], // Cumple
        [5, 'B', null], // Sin evaluación
        [6, 'C', 'A'], // Supera
        [7, 'A', 'B', 'Especifica'], // Baja, -1
        [8, 'B', 'D'], // Alta, -2
    ]]);

    $datos = $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->json('data');

    expect($datos['dnf']['codigo'])->toBe('DNF-0002')
        ->and($datos['puesto'])->toBe(['codigo' => 'PU-001', 'nombre' => 'Puesto 1'])
        ->and($datos['resumen'])->toBe(['con_brecha' => 5, 'cumplen' => 2, 'sin_evaluacion' => 1])
        ->and(array_column($datos['prioritarias'], 'competencia_id'))->toBe([3, 1, 8])
        ->and(array_column($datos['brechas'], 'competencia_id'))->toBe([3, 1, 8, 2, 7])
        ->and(array_column($datos['brechas'], 'prioridad'))->toBe(['Critica', 'Alta', 'Alta', 'Media', 'Baja'])
        ->and(array_column($datos['cumplen'], 'competencia_id'))->toBe([4, 6]);
});

it('a igual prioridad ordena por mayor diferencia y luego por nombre', function () {
    dnfFalsos();
    $colaborador = actuarComoColaboradorConFicha();
    dnfDeEjemplo([$colaborador => [
        [10, 'B', 'C'], // Media, -1
        [11, 'A', 'B'], // Media, -1
        [12, 'A', 'C'], // Alta, -2
        [13, 'A', 'D'], // Crítica, -3
    ]]);

    $datos = $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->json('data');

    expect(array_column($datos['prioritarias'], 'competencia_id'))->toBe([13, 12, 10]);
});

it('la DNF masiva más reciente reemplaza a una individual anterior', function () {
    dnfFalsos();
    $colaborador = actuarComoColaboradorConFicha();
    dnfDeEjemplo([$colaborador => [[1, 'A', 'D']]]);
    $masiva = dnfDeEjemplo([$colaborador => [[2, 'A', 'C']], 999 => [[2, 'A', 'D']]], 'Departamento', 5);

    $datos = $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->json('data');

    expect($datos['dnf']['id'])->toBe($masiva->id)
        ->and(array_column($datos['brechas'], 'competencia_id'))->toBe([2]);
});

it('indica por competencia si hay cursos o micro-contenidos disponibles', function () {
    dnfFalsos();
    $colaborador = actuarComoColaboradorConFicha();
    configurarFormativa(1, null, [CursoModel::factory()->create()->id]);
    configurarFormativa(2, null, [CursoModel::factory()->archivado()->create()->id]);
    dnfDeEjemplo([$colaborador => [[1, 'A', 'C'], [2, 'A', 'C'], [3, 'A', 'C']]]);

    $oferta = $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->json('data.tiene_oferta');

    expect($oferta)->toBe(['1' => true, '2' => false, '3' => false]);
});

it('sin DNF devuelve data null', function () {
    dnfFalsos();
    actuarComoColaboradorConFicha();

    $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->assertExactJson(['data' => null]);
});

it('sin ficha asociada devuelve data null', function () {
    dnfFalsos();
    actuarComoUsuarioDeTenant();
    dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->assertExactJson(['data' => null]);
});

it('una DNF sin brechas devuelve listas vacías y las competencias que cumple', function () {
    dnfFalsos();
    $colaborador = actuarComoColaboradorConFicha();
    dnfDeEjemplo([$colaborador => [[1, 'B', 'B'], [2, 'C', 'A']]]);

    $datos = $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->json('data');

    expect($datos['brechas'])->toBe([])
        ->and($datos['prioritarias'])->toBe([])
        ->and($datos['resumen'])->toBe(['con_brecha' => 0, 'cumplen' => 2, 'sin_evaluacion' => 0])
        ->and($datos['tiene_oferta'])->toBe([]);
});

it('un colaborador solo ve su propio diagnóstico, sin aceptar parámetros', function () {
    dnfFalsos();
    $propio = actuarComoColaboradorConFicha();
    $ajeno = (int) fichaDe([], 1)->id;
    dnfDeEjemplo([$ajeno => [[1, 'A', 'D']]]);
    dnfDeEjemplo([$propio => [[2, 'A', 'C']]]);

    $datos = $this->getJson('/api/dnf/mi-diagnostico?colaborador_id='.$ajeno)->assertOk()->json('data');

    expect(array_column($datos['brechas'], 'competencia_id'))->toBe([2]);
});

it('no mezcla DNF de otro tenant', function () {
    dnfFalsos();
    $colaborador = actuarComoColaboradorConFicha();
    tenantDos();
    dnfDeEjemplo([$colaborador => [[1, 'A', 'D']]], tenantId: 2);

    $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->assertExactJson(['data' => null]);
});

it('exige autenticación', function () {
    $this->getJson('/api/dnf/mi-diagnostico')->assertUnauthorized();
});

it('un administrador sin ficha recibe data null', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();

    $this->getJson('/api/dnf/mi-diagnostico')->assertOk()->assertExactJson(['data' => null]);
});
