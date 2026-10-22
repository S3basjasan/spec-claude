<?php

use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ParametrosAdecuacionModel;

/**
 * Cuatro candidatos para el puesto 2:
 *  - 10: competencias 3/4 de cumplimiento (C frente a B…): 1 brecha, experiencia 3 de 5 años.
 *  - 11: todo cumple.
 *  - 12: preparación Incompleta (un requisito sin evaluación).
 *  - 13: sin adecuación para este puesto.
 */
function candidatosDeComparacion(): void
{
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    adecuacionEnBd([
        ['id' => 1, 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'C'],
        ['anios' => [5.0, 3.0], 'tiempo' => 12],
    ], ['colaborador' => 10, 'puesto' => 2, 'experiencia' => 5.0]);
    adecuacionEnBd([
        ['id' => 1, 'objetivo' => 'B', 'valor' => 'B'],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'A'],
        ['anios' => [5.0, 6.0]],
    ], ['colaborador' => 11, 'puesto' => 2, 'experiencia' => 5.0]);
    adecuacionEnBd([
        ['id' => 1, 'objetivo' => 'B', 'valor' => 'B'],
        ['id' => 3, 'objetivo' => 'C', 'valor' => null],
    ], ['colaborador' => 12, 'puesto' => 2]);
}

function compararCandidatosPorHttp(object $prueba, string $consulta)
{
    return $prueba->getJson("/api/adecuaciones/comparar?{$consulta}");
}

const CANDIDATOS = 'puesto_objetivo_id=2&colaborador_ids[]=10&colaborador_ids[]=11&colaborador_ids[]=12&colaborador_ids[]=13';

it('calcula el ranking con los pesos guardados y deja los Incompletos y los sin adecuación aparte', function () {
    candidatosDeComparacion();

    $d = compararCandidatosPorHttp($this, CANDIDATOS)->assertOk()->json('data');

    expect($d['puesto_objetivo'])->toBe(['id' => 2, 'nombre' => 'Analista de datos'])
        ->and($d['pesos'])->toBe(['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30])
        ->and(array_column($d['ranking'], 'posicion'))->toBe([1, 2])
        ->and(array_column(array_column($d['ranking'], 'colaborador'), 'id'))->toBe([11, 10])
        ->and($d['incompletos'])->toHaveCount(1)->and($d['incompletos'][0]['colaborador']['id'])->toBe(12)
        ->and($d['incompletos'][0])->not->toHaveKey('puntaje')
        ->and($d['sin_adecuacion'])->toHaveCount(1)->and($d['sin_adecuacion'][0]['colaborador']['id'])->toBe(13);
});

it('el puntaje reescala los pesos sin conocimientos y aplica el tope de 1', function () {
    candidatosDeComparacion();

    $ranking = collect(compararCandidatosPorHttp($this, CANDIDATOS)->json('data.ranking'))->keyBy(fn ($r) => $r['colaborador']['id']);

    // Candidato 11: competencias 1,0 (tope), experiencia 1,0 (6 años de 5 con tope) → 100 %.
    expect($ranking[11]['puntaje'])->toEqual(100.0)
        ->and($ranking[11]['preparacion'])->toBe('Lista')
        ->and($ranking[11]['brechas'])->toBe(0);
    // Candidato 10: competencias (2/3 + 1) / 2 = 0,8333; experiencia 0,6; pesos 40 y 30 reescalados a 4/7 y 3/7 → 73,3 %.
    expect($ranking[10]['puntaje'])->toBe(73.3)
        ->and($ranking[10]['brechas'])->toBe(2)
        ->and($ranking[10]['preparacion'])->toBe('EnDesarrollo')
        ->and($ranking[10]['cumplimiento_por_dimension']['Conocimiento'])->toBeNull();
});

it('los pesos de la consulta cambian el ranking pero no los parámetros guardados', function () {
    candidatosDeComparacion();

    $r = compararCandidatosPorHttp($this, CANDIDATOS.'&peso_competencias=10&peso_conocimientos=0&peso_experiencia=90')->assertOk()->json('data');
    $ranking = collect($r['ranking'])->keyBy(fn ($x) => $x['colaborador']['id']);

    // Candidato 10: 0,8333 * (10/100) + 0,6 * (90/100) = 0,6233 → 62,3 %.
    expect($r['pesos'])->toBe(['Competencia' => 10, 'Conocimiento' => 0, 'Experiencia' => 90])
        ->and($ranking[10]['puntaje'])->toBe(62.3);
    expect(app(AdecuacionesRepositoryInterface::class)->obtenerParametros(1)->pesos->comoArreglo())->toBe(['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30]);
});

it('usa los pesos guardados cuando cambiaron', function () {
    candidatosDeComparacion();
    ParametrosAdecuacionModel::query()->create(['tenant_id' => 1, 'peso_competencias' => 100, 'peso_conocimientos' => 0, 'peso_experiencia' => 0, 'umbral_meses' => 12]);

    $d = compararCandidatosPorHttp($this, CANDIDATOS)->assertOk()->json('data');

    expect($d['pesos']['Competencia'])->toBe(100)
        ->and(collect($d['ranking'])->firstWhere('colaborador.id', 10)['puntaje'])->toBe(83.3);
});

it('usa la adecuación vigente de cada candidato', function () {
    candidatosDeComparacion();
    adecuacionEnBd([
        ['id' => 1, 'objetivo' => 'B', 'valor' => 'B'],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'C'],
        ['anios' => [5.0, 5.0]],
    ], ['colaborador' => 10, 'puesto' => 2, 'experiencia' => 5.0]);

    $ranking = collect(compararCandidatosPorHttp($this, CANDIDATOS)->json('data.ranking'))->keyBy(fn ($r) => $r['colaborador']['id']);

    expect($ranking[10]['brechas'])->toBe(0)->and($ranking[10]['puntaje'])->toEqual(100.0);
});

it('rechaza con 422 pesos que no suman 100, negativos, incompletos, ids repetidos y cantidades inválidas', function () {
    candidatosDeComparacion();

    compararCandidatosPorHttp($this, CANDIDATOS.'&peso_competencias=50&peso_conocimientos=30&peso_experiencia=30')->assertStatus(422)->assertJsonPath('code', 'pesos_invalidos');
    compararCandidatosPorHttp($this, CANDIDATOS.'&peso_competencias=-10&peso_conocimientos=60&peso_experiencia=50')->assertStatus(422);
    compararCandidatosPorHttp($this, CANDIDATOS.'&peso_competencias=100')->assertStatus(422);
    compararCandidatosPorHttp($this, 'puesto_objetivo_id=2&colaborador_ids[]=10&colaborador_ids[]=10')->assertStatus(422);
    compararCandidatosPorHttp($this, 'puesto_objetivo_id=2&colaborador_ids[]=10')->assertStatus(422);
    compararCandidatosPorHttp($this, 'puesto_objetivo_id=2&'.implode('&', array_map(fn ($i) => "colaborador_ids[]={$i}", range(1, 21))))->assertStatus(422);
    compararCandidatosPorHttp($this, 'colaborador_ids[]=10&colaborador_ids[]=11')->assertStatus(422);
    compararCandidatosPorHttp($this, 'puesto_objetivo_id=99&colaborador_ids[]=10&colaborador_ids[]=11')->assertStatus(422)->assertJsonPath('code', 'objetivo_no_definido');
});

it('no guarda nada: la comparación se calcula en el momento', function () {
    candidatosDeComparacion();
    $antes = AdecuacionModel::query()->count();
    $parametros = ParametrosAdecuacionModel::query()->count();

    compararCandidatosPorHttp($this, CANDIDATOS)->assertOk();
    compararCandidatosPorHttp($this, CANDIDATOS.'&peso_competencias=10&peso_conocimientos=0&peso_experiencia=90')->assertOk();

    expect(AdecuacionModel::query()->count())->toBe($antes)->and(ParametrosAdecuacionModel::query()->count())->toBeLessThanOrEqual($parametros + 1);
});

it('solo considera adecuaciones de su tenant', function () {
    candidatosDeComparacion();
    adecuacionEnBd([['id' => 1, 'objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 13, 'puesto' => 2, 'tenant' => 2]);

    $d = compararCandidatosPorHttp($this, CANDIDATOS)->assertOk()->json('data');

    expect($d['sin_adecuacion'])->toHaveCount(1)->and($d['sin_adecuacion'][0]['colaborador']['id'])->toBe(13);
});

it('exige la clave de comparar y sesión', function () {
    compararCandidatosPorHttp($this, CANDIDATOS)->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    compararCandidatosPorHttp($this, CANDIDATOS)->assertForbidden();
});
