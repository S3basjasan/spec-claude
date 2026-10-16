<?php

use App\Modules\GestionPersonal\Domain\Services\ReglasDeDesarrollo;

const HOY_DESARROLLO = '2026-10-15';

function formacionValida(array $cambios = []): array
{
    return array_replace([
        'nivel' => 'Tercer nivel', 'titulo_programa' => 'Ingeniería', 'institucion' => 'EPN', 'estado' => 'Completada',
        'fecha_inicio' => null, 'fecha_finalizacion' => null, 'observaciones' => null,
    ], $cambios);
}

function experienciaValida(array $cambios = []): array
{
    return array_replace([
        'organizacion' => 'ACME', 'cargo' => 'Analista', 'fecha_inicio' => '2019-01-07',
        'fecha_finalizacion' => null, 'funciones' => null, 'observaciones' => null,
    ], $cambios);
}

it('acepta una formación mínima sin fechas', function () {
    expect((new ReglasDeDesarrollo)->erroresFormacion(formacionValida(), HOY_DESARROLLO))->toBe([]);
});

it('exige los campos obligatorios y trata los espacios como vacío', function () {
    $reglas = new ReglasDeDesarrollo;
    $estado = $reglas->normalizar(formacionValida(['nivel' => '   ', 'institucion' => null]));

    expect($reglas->erroresFormacion($estado, HOY_DESARROLLO))->toHaveKeys(['nivel', 'institucion'])
        ->and($reglas->erroresExperiencia($reglas->normalizar(experienciaValida(['organizacion' => ' ', 'cargo' => null, 'fecha_inicio' => null])), HOY_DESARROLLO))
        ->toHaveKeys(['organizacion', 'cargo', 'fecha_inicio']);
});

it('aplica cada límite en el máximo y rechaza máximo más uno', function (string $campo, int $max, bool $esFormacion) {
    $reglas = new ReglasDeDesarrollo;
    $valido = fn (int $n) => $esFormacion ? formacionValida([$campo => str_repeat('a', $n)]) : experienciaValida([$campo => str_repeat('a', $n)]);
    $errores = fn (array $e) => $esFormacion ? $reglas->erroresFormacion($e, HOY_DESARROLLO) : $reglas->erroresExperiencia($e, HOY_DESARROLLO);

    expect($errores($valido($max)))->toBe([])
        ->and($errores($valido($max + 1)))->toHaveKey($campo);
})->with([
    ['nivel', 100, true], ['titulo_programa', 200, true], ['institucion', 200, true], ['observaciones', 2000, true],
    ['organizacion', 200, false], ['cargo', 200, false], ['funciones', 2000, false], ['observaciones', 2000, false],
]);

it('la finalización igual al inicio es válida y anterior no', function () {
    $reglas = new ReglasDeDesarrollo;

    expect($reglas->erroresFormacion(formacionValida(['fecha_inicio' => '2020-01-01', 'fecha_finalizacion' => '2020-01-01']), HOY_DESARROLLO))->toBe([])
        ->and($reglas->erroresFormacion(formacionValida(['fecha_inicio' => '2020-01-02', 'fecha_finalizacion' => '2020-01-01']), HOY_DESARROLLO))->toHaveKey('fecha_finalizacion')
        ->and($reglas->erroresExperiencia(experienciaValida(['fecha_inicio' => '2020-01-02', 'fecha_finalizacion' => '2020-01-01']), HOY_DESARROLLO))->toHaveKey('fecha_finalizacion');
});

it('el inicio futuro se rechaza en formación y experiencia', function () {
    $reglas = new ReglasDeDesarrollo;

    expect($reglas->erroresFormacion(formacionValida(['fecha_inicio' => '2026-10-16']), HOY_DESARROLLO))->toHaveKey('fecha_inicio')
        ->and($reglas->erroresExperiencia(experienciaValida(['fecha_inicio' => '2026-10-16']), HOY_DESARROLLO))->toHaveKey('fecha_inicio')
        ->and($reglas->erroresFormacion(formacionValida(['fecha_inicio' => HOY_DESARROLLO]), HOY_DESARROLLO))->toBe([]);
});

it('la finalización futura es válida en formación pero no en experiencia', function () {
    $reglas = new ReglasDeDesarrollo;

    expect($reglas->erroresFormacion(formacionValida(['estado' => 'EnCurso', 'fecha_finalizacion' => '2030-01-01']), HOY_DESARROLLO))->toBe([])
        ->and($reglas->erroresExperiencia(experienciaValida(['fecha_finalizacion' => '2030-01-01']), HOY_DESARROLLO))->toHaveKey('fecha_finalizacion');
});

it('rechaza fechas que no existen o con formato distinto', function () {
    $reglas = new ReglasDeDesarrollo;

    expect($reglas->erroresFormacion(formacionValida(['fecha_inicio' => '2020-02-30']), HOY_DESARROLLO))->toHaveKey('fecha_inicio')
        ->and($reglas->erroresExperiencia(experienciaValida(['fecha_inicio' => '15/10/2020']), HOY_DESARROLLO))->toHaveKey('fecha_inicio');
});

it('el estado no deduce ni exige fechas y debe ser válido', function () {
    $reglas = new ReglasDeDesarrollo;

    foreach (['EnCurso', 'Completada', 'Incompleta'] as $estado) {
        expect($reglas->erroresFormacion(formacionValida(['estado' => $estado]), HOY_DESARROLLO))->toBe([]);
    }
    expect($reglas->erroresFormacion(formacionValida(['estado' => 'Otro']), HOY_DESARROLLO))->toHaveKey('estado');
});

it('valida el estado y las observaciones de movilidad', function () {
    $reglas = new ReglasDeDesarrollo;

    expect($reglas->erroresMovilidad(['estado' => 'Disponible', 'observaciones' => str_repeat('a', 2000)]))->toBe([])
        ->and($reglas->erroresMovilidad(['estado' => 'Disponible', 'observaciones' => str_repeat('a', 2001)]))->toHaveKey('observaciones')
        ->and($reglas->erroresMovilidad(['estado' => 'Quizá', 'observaciones' => null]))->toHaveKey('estado');
});
