<?php

use App\Modules\Desarrollo\Domain\Exceptions\QuizInvalido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeQuiz;

function preguntaQuiz(int $opciones = 3, int $correctas = 1, string $enunciado = '¿Qué es?'): array
{
    $lista = [];
    for ($i = 0; $i < $opciones; $i++) {
        $lista[] = ['texto' => 'Opción '.($i + 1), 'es_correcta' => $i < $correctas];
    }

    return ['enunciado' => $enunciado, 'opciones' => $lista];
}

it('acepta de 1 a 20 preguntas con 2 a 5 opciones y una correcta', function () {
    $reglas = new ReglasDeQuiz;

    $reglas->validar([preguntaQuiz(2)], true);
    $reglas->validar(array_fill(0, 20, preguntaQuiz(5)), true);

    expect(true)->toBeTrue();
});

it('un borrador puede guardarse sin preguntas pero no publicarse', function () {
    $reglas = new ReglasDeQuiz;

    $reglas->validar([]);

    expect(fn () => $reglas->validar([], true))->toThrow(QuizInvalido::class);
});

it('rechaza más de 20 preguntas', function () {
    expect(fn () => (new ReglasDeQuiz)->validar(array_fill(0, 21, preguntaQuiz())))->toThrow(QuizInvalido::class);
});

it('rechaza menos de 2 o más de 5 opciones', function (int $opciones) {
    try {
        (new ReglasDeQuiz)->validar([preguntaQuiz($opciones)]);
        $this->fail('Debió lanzar QuizInvalido');
    } catch (QuizInvalido $e) {
        expect(array_keys($e->errores()))->toContain('preguntas.0.opciones');
    }
})->with([1, 6]);

it('exige exactamente una opción correcta por pregunta', function (int $correctas) {
    try {
        (new ReglasDeQuiz)->validar([preguntaQuiz(3, $correctas)]);
        $this->fail('Debió lanzar QuizInvalido');
    } catch (QuizInvalido $e) {
        expect(array_keys($e->errores()))->toContain('preguntas.0.correcta');
    }
})->with([0, 2]);

it('exige enunciado y texto en cada opción', function () {
    $pregunta = preguntaQuiz(2, 1, '   ');
    $pregunta['opciones'][1]['texto'] = '';

    try {
        (new ReglasDeQuiz)->validar([$pregunta]);
        $this->fail('Debió lanzar QuizInvalido');
    } catch (QuizInvalido $e) {
        expect(array_keys($e->errores()))->toContain('preguntas.0.enunciado')->toContain('preguntas.0.opciones.1.texto');
    }
});

it('reconoce si las preguntas pedidas son las guardadas', function () {
    $reglas = new ReglasDeQuiz;
    $a = [preguntaQuiz(3, 1, 'Uno'), preguntaQuiz(2, 1, 'Dos')];

    expect($reglas->iguales($a, $a))->toBeTrue()
        ->and($reglas->iguales($a, [$a[1], $a[0]]))->toBeFalse()
        ->and($reglas->iguales($a, [$a[0]]))->toBeFalse();
});
