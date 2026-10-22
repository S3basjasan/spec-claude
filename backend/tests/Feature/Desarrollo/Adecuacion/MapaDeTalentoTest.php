<?php

use Illuminate\Support\Facades\DB;

/**
 * Datos del mapa:
 *  - 10 (Tecnología): puesto 2 con dos adecuaciones (la vieja no cuenta); y puesto 3 por puesto directo (NoLista, 2 brechas).
 *  - 11 (Tecnología): puesto 2 por la ruta A (Lista).
 *  - 12 (Talento Humano): puesto 2 por la ruta B (Incompleta).
 */
function datosDelMapa(): array
{
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $rutaA = rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta A']);
    $rutaB = rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta B']);
    $insA = inscripcionEnBd($rutaA['id'], 11, $rutaA['niveles'][0], $rutaA['niveles'][1]);
    $insB = inscripcionEnBd($rutaB['id'], 12, $rutaB['niveles'][0], $rutaB['niveles'][1]);

    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'D', 'tiempo' => 30]], ['colaborador' => 10, 'puesto' => 2, 'creada' => '2026-01-01 08:00:00']);
    adecuacionEnBd([['id' => 1, 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 4], ['id' => 2, 'objetivo' => 'C', 'valor' => 'C']], ['colaborador' => 10, 'puesto' => 2]);
    adecuacionEnBd([['id' => 1, 'objetivo' => 'A', 'valor' => 'C', 'tiempo' => 24], ['id' => 2, 'objetivo' => 'B', 'valor' => 'D', 'tiempo' => 9]], ['colaborador' => 10, 'puesto' => 3]);
    adecuacionEnBd([['id' => 1, 'objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11, 'puesto' => 2, 'inscripcion' => $insA->id, 'nivel' => $rutaA['niveles'][1]]);
    adecuacionEnBd([['id' => 1, 'objetivo' => 'B', 'valor' => null]], ['colaborador' => 12, 'puesto' => 2, 'inscripcion' => $insB->id, 'nivel' => $rutaB['niveles'][1]]);

    return [$rutaA, $rutaB];
}

it('muestra una fila por colaborador y puesto objetivo con la adecuación más reciente', function () {
    datosDelMapa();

    $r = $this->getJson('/api/adecuaciones/mapa')->assertOk()->json();

    expect($r['data'])->toHaveCount(4)->and($r['meta']['total'])->toBe(4);
    $pares = collect($r['data'])->map(fn ($f) => $f['colaborador']['id'].'→'.$f['puesto_objetivo']['id'])->sort()->values()->all();
    expect($pares)->toBe(['10→2', '10→3', '11→2', '12→2']);
    $fila = collect($r['data'])->first(fn ($f) => $f['colaborador']['id'] === 10 && $f['puesto_objetivo']['id'] === 2);
    expect($fila['preparacion'])->toBe('EnDesarrollo')->and($fila['brechas'])->toBe(1);
});

it('calcula brechas, tiempo máximo y los nombres de colaborador, departamento, ruta y puestos', function () {
    datosDelMapa();

    $filas = collect($this->getJson('/api/adecuaciones/mapa')->assertOk()->json('data'))->keyBy(fn ($f) => $f['colaborador']['id'].':'.$f['puesto_objetivo']['id']);

    expect($filas['10:3'])->toMatchArray(['preparacion' => 'NoLista', 'brechas' => 2, 'tiempo_maximo_cierre_meses' => 24])
        ->and($filas['10:3']['ruta'])->toBeNull()
        ->and($filas['10:3']['colaborador']['departamento'])->toBe('Tecnología')
        ->and($filas['10:3']['puesto_actual'])->toBe(['id' => 1, 'nombre' => 'Puesto 1'])
        ->and($filas['10:3']['puesto_objetivo']['nombre'])->toBe('Puesto 3')
        ->and($filas['11:2']['ruta']['nombre'])->toBe('Ruta A')
        ->and($filas['11:2'])->toMatchArray(['preparacion' => 'Lista', 'brechas' => 0, 'tiempo_maximo_cierre_meses' => null])
        ->and($filas['12:2']['colaborador']['departamento'])->toBe('Talento Humano')
        ->and($filas['12:2']['preparacion'])->toBe('Incompleta');
});

it('filtra por departamento, ruta y preparación, solos y combinados', function () {
    [$rutaA] = datosDelMapa();
    $pares = fn (string $q) => collect($this->getJson("/api/adecuaciones/mapa?{$q}")->assertOk()->json('data'))->map(fn ($f) => $f['colaborador']['id'].':'.$f['puesto_objetivo']['id'])->sort()->values()->all();

    expect($pares('departamento_id=1'))->toBe(['10:2', '10:3', '11:2'])
        ->and($pares('departamento_id=2'))->toBe(['12:2'])
        ->and($pares("ruta_id={$rutaA['id']}"))->toBe(['11:2'])
        ->and($pares('preparacion=NoLista'))->toBe(['10:3'])
        ->and($pares('departamento_id=1&preparacion=Lista'))->toBe(['11:2'])
        ->and($pares('departamento_id=2&preparacion=Lista'))->toBe([]);
});

it('los indicadores cuentan por preparación sobre el conjunto filtrado por departamento y ruta', function () {
    datosDelMapa();

    expect($this->getJson('/api/adecuaciones/mapa')->json('kpis'))->toBe(['Lista' => 1, 'EnDesarrollo' => 1, 'NoLista' => 1, 'Incompleta' => 1]);
    expect($this->getJson('/api/adecuaciones/mapa?departamento_id=1')->json('kpis'))->toBe(['Lista' => 1, 'EnDesarrollo' => 1, 'NoLista' => 1, 'Incompleta' => 0]);
    // Con el filtro de preparación la lista se reduce pero los indicadores siguen mostrando la distribución.
    $r = $this->getJson('/api/adecuaciones/mapa?preparacion=Lista')->json();
    expect($r['data'])->toHaveCount(1)->and($r['kpis'])->toBe(['Lista' => 1, 'EnDesarrollo' => 1, 'NoLista' => 1, 'Incompleta' => 1]);
});

it('pagina la tabla', function () {
    datosDelMapa();

    $r = $this->getJson('/api/adecuaciones/mapa?per_page=3&page=2')->assertOk()->json();

    expect($r['data'])->toHaveCount(1)->and($r['meta'])->toBe(['page' => 2, 'per_page' => 3, 'total' => 4, 'last_page' => 2]);
});

it('valida los filtros', function () {
    datosDelMapa();

    $this->getJson('/api/adecuaciones/mapa?preparacion=Otra')->assertStatus(422)->assertJsonValidationErrors('preparacion');
    $this->getJson('/api/adecuaciones/mapa?departamento_id=abc')->assertStatus(422)->assertJsonValidationErrors('departamento_id');
});

it('degrada con un aviso si la fuente de departamentos falla', function () {
    datosDelMapa();
    departamentosActualesCaidos();

    $r = $this->getJson('/api/adecuaciones/mapa')->assertOk()->json();

    expect($r['fuentes_no_disponibles'])->toContain('personal')->and($r['data'])->toHaveCount(4);
});

it('solo incluye adecuaciones de su tenant', function () {
    datosDelMapa();
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 13, 'tenant' => 2]);

    expect($this->getJson('/api/adecuaciones/mapa')->json('meta.total'))->toBe(4);
});

it('no hace consultas por fila (50 colaboradores)', function () {
    actuarComoAdminDeTenant();
    $vigentes = [];
    foreach (range(100, 150) as $id) {
        $vigentes[$id] = "Colaborador {$id}";
    }
    adecuacionFalsos(['vigentes' => $vigentes, 'unidades' => array_fill_keys(range(100, 150), 1)]);
    foreach (range(100, 150) as $id) {
        adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 5]], ['colaborador' => $id, 'puesto' => 2]);
    }

    DB::enableQueryLog();
    $this->getJson('/api/adecuaciones/mapa?per_page=100')->assertOk()->assertJsonCount(51, 'data');
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBeLessThan(16);
});

it('exige la clave del mapa y sesión', function () {
    $this->getJson('/api/adecuaciones/mapa')->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/adecuaciones/mapa')->assertForbidden();
});
