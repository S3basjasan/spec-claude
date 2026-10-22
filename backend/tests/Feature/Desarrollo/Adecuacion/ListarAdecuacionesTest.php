<?php

use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use Illuminate\Support\Facades\DB;

function variasAdecuaciones(): array
{
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $lista = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 10, 'puesto' => 2, 'creada' => '2026-01-10 09:00:00']);
    $enDesarrollo = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]], ['colaborador' => 10, 'puesto' => 3, 'creada' => '2026-02-15 09:00:00']);
    $noLista = adecuacionEnBd([['objetivo' => 'A', 'valor' => 'D', 'tiempo' => 30], ['id' => 2, 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 3]], ['colaborador' => 11, 'puesto' => 3, 'creada' => '2026-03-20 09:00:00']);
    $nueva = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 4]], ['colaborador' => 10, 'puesto' => 2]);

    return compact('lista', 'enDesarrollo', 'noLista', 'nueva');
}

it('lista las adecuaciones de la más reciente a la más antigua con código, colaborador, puesto, preparación y brechas', function () {
    ['nueva' => $nueva, 'noLista' => $noLista] = variasAdecuaciones();

    $r = $this->getJson('/api/adecuaciones')->assertOk()->json();

    expect(array_column($r['data'], 'id'))->toHaveCount(4)
        ->and($r['data'][0])->toMatchArray([
            'id' => $nueva->id, 'codigo' => 'ADE-0004', 'preparacion' => 'EnDesarrollo', 'brechas' => 1, 'es_vigente' => true,
        ])
        ->and($r['data'][0]['colaborador'])->toBe(['id' => 10, 'nombre' => 'Colaborador 10'])
        ->and($r['data'][0]['puesto_objetivo'])->toBe(['id' => 2, 'nombre' => 'Puesto 2'])
        ->and($r['meta'])->toBe(['page' => 1, 'per_page' => 15, 'total' => 4, 'last_page' => 1]);
    $porId = collect($r['data'])->keyBy('id');
    expect($porId[$noLista->id])->toMatchArray(['preparacion' => 'NoLista', 'brechas' => 2]);
});

it('marca como vigente solo la más reciente de cada colaborador y puesto objetivo', function () {
    ['lista' => $vieja, 'nueva' => $nueva] = variasAdecuaciones();

    $porId = collect($this->getJson('/api/adecuaciones')->assertOk()->json('data'))->keyBy('id');

    expect($porId[$vieja->id]['es_vigente'])->toBeFalse()->and($porId[$nueva->id]['es_vigente'])->toBeTrue();
});

it('filtra por fecha, colaborador, puesto objetivo y preparación, solos y combinados', function () {
    variasAdecuaciones();

    $ids = fn (string $consulta) => array_column($this->getJson("/api/adecuaciones?{$consulta}")->assertOk()->json('data'), 'codigo');

    expect($ids('colaborador_id=11'))->toBe(['ADE-0003'])
        ->and($ids('puesto_objetivo_id=3'))->toBe(['ADE-0003', 'ADE-0002'])
        ->and($ids('preparacion=Lista'))->toBe(['ADE-0001'])
        ->and($ids('fecha_desde=2026-02-01&fecha_hasta=2026-03-31'))->toBe(['ADE-0003', 'ADE-0002'])
        ->and($ids('fecha_hasta=2026-01-31'))->toBe(['ADE-0001'])
        ->and($ids('colaborador_id=10&puesto_objetivo_id=3&preparacion=EnDesarrollo'))->toBe(['ADE-0002'])
        ->and($ids('colaborador_id=11&preparacion=Lista'))->toBe([]);
});

it('pagina el historial', function () {
    variasAdecuaciones();

    $r = $this->getJson('/api/adecuaciones?per_page=3&page=2')->assertOk()->json();

    expect($r['data'])->toHaveCount(1)
        ->and($r['data'][0]['codigo'])->toBe('ADE-0001')
        ->and($r['meta'])->toBe(['page' => 2, 'per_page' => 3, 'total' => 4, 'last_page' => 2]);
});

it('valida los filtros y la paginación', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();

    $this->getJson('/api/adecuaciones?preparacion=Otra')->assertStatus(422)->assertJsonValidationErrors('preparacion');
    $this->getJson('/api/adecuaciones?fecha_desde=2026-13-01')->assertStatus(422)->assertJsonValidationErrors('fecha_desde');
    $this->getJson('/api/adecuaciones?fecha_desde=2026-05-01&fecha_hasta=2026-01-01')->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');
    $this->getJson('/api/adecuaciones?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');
});

it('solo lista las adecuaciones de su tenant', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['tenant' => 2]);

    expect($this->getJson('/api/adecuaciones')->assertOk()->json('meta.total'))->toBe(1);
});

it('lista sin consultas por fila (sin N+1)', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    foreach (range(1, 12) as $i) {
        adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 5]], ['colaborador' => 10 + ($i % 3)]);
    }

    DB::enableQueryLog();
    $this->getJson('/api/adecuaciones')->assertOk();
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBeLessThan(16);
});

it('devuelve el detalle de solo lectura con resultados, quién la generó y las acciones deshabilitadas', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'C'],
    ], ['observaciones' => 'Revisar en seis meses']);

    $d = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data');

    expect($d)->toMatchArray(['id' => $a->id, 'codigo' => 'ADE-0001', 'preparacion' => 'EnDesarrollo', 'umbral_meses' => 12, 'observaciones' => 'Revisar en seis meses', 'es_vigente' => true, 'brechas' => 1, 'tiempo_maximo_cierre_meses' => 6])
        ->and($d['resultados'])->toHaveCount(2)
        ->and($d['resultados'][0])->toMatchArray(['estado' => 'ConBrecha', 'diferencia' => -1, 'tiempo_cierre_meses' => 6])
        ->and($d['colaborador']['id'])->toBe(10)
        ->and($d['puesto_objetivo']['id'])->toBe(2)
        ->and($d['acciones']['crear_plan'])->toBe(['habilitado' => false, 'motivo' => 'Disponible cuando exista el Plan de Desarrollo Individual.'])
        ->and($d['acciones']['incluir_programa']['habilitado'])->toBeFalse();
});

it('en el detalle sin brechas el motivo de las acciones es «No hay brechas.»', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);

    $d = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data');

    expect($d['acciones']['crear_plan']['motivo'])->toBe('No hay brechas.')->and($d['tiempo_maximo_cierre_meses'])->toBeNull();
});

it('el detalle indica si la adecuación fue reemplazada por una más reciente', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $vieja = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['creada' => '2026-01-01 09:00:00']);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);

    expect($this->getJson("/api/adecuaciones/{$vieja->id}")->assertOk()->json('data.es_vigente'))->toBeFalse();
});

it('responde 404 si la adecuación no existe o es de otro tenant', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $ajena = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['tenant' => 2]);

    $this->getJson("/api/adecuaciones/{$ajena->id}")->assertNotFound()->assertJsonPath('code', 'adecuacion_no_encontrada');
    $this->getJson('/api/adecuaciones/9999')->assertNotFound();
    expect(app(AdecuacionesRepositoryInterface::class)->obtener(1, $ajena->id))->toBeNull();
});

it('el historial y el detalle exigen la clave «ver» y sesión', function () {
    $this->getJson('/api/adecuaciones')->assertUnauthorized();
    $this->getJson('/api/adecuaciones/1')->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/adecuaciones')->assertForbidden();
    $this->getJson('/api/adecuaciones/1')->assertForbidden();
});
