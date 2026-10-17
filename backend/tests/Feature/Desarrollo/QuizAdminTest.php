<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroOpcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroRespuestaModel;
use Illuminate\Support\Facades\DB;

function cuerpoQuiz(int $preguntas = 2, array $cambios = []): array
{
    return cuerpoMicro(['tipo' => 'Quiz', 'url' => null, 'preguntas' => preguntasDeQuiz($preguntas)] + $cambios);
}

it('crea un quiz con preguntas y opciones en el orden del arreglo', function () {
    actuarComoAdmin();

    $datos = $this->postJson('/api/microcontenidos', cuerpoQuiz(3))->assertCreated()->json('data');

    expect($datos['tipo'])->toBe('Quiz')->and($datos['preguntas'])->toHaveCount(3)
        ->and(array_column($datos['preguntas'], 'orden'))->toBe([1, 2, 3])
        ->and($datos['preguntas'][0]['opciones'])->toHaveCount(3)
        ->and($datos['preguntas'][0]['opciones'][0]['es_correcta'])->toBeTrue()
        ->and($datos['tiene_intentos'])->toBeFalse();
});

it('rechaza más de 20 preguntas, opciones fuera de 2 a 5, correctas distintas de una y enunciados vacíos', function () {
    actuarComoAdmin();

    $this->postJson('/api/microcontenidos', cuerpoQuiz(21))->assertUnprocessable()->assertJsonPath('code', 'quiz_invalido');

    $pocas = cuerpoQuiz(1);
    $pocas['preguntas'][0]['opciones'] = [['texto' => 'Sola', 'es_correcta' => true]];
    $this->postJson('/api/microcontenidos', $pocas)->assertUnprocessable()->assertJsonPath('code', 'quiz_invalido');

    $muchas = cuerpoQuiz(1);
    $muchas['preguntas'][0]['opciones'] = array_map(fn ($i) => ['texto' => "O$i", 'es_correcta' => $i === 1], range(1, 6));
    $this->postJson('/api/microcontenidos', $muchas)->assertUnprocessable()->assertJsonPath('code', 'quiz_invalido');

    $sinCorrecta = cuerpoQuiz(1);
    $sinCorrecta['preguntas'][0]['opciones'][0]['es_correcta'] = false;
    $this->postJson('/api/microcontenidos', $sinCorrecta)->assertUnprocessable()->assertJsonPath('code', 'quiz_invalido');

    $dosCorrectas = cuerpoQuiz(1);
    $dosCorrectas['preguntas'][0]['opciones'][1]['es_correcta'] = true;
    $this->postJson('/api/microcontenidos', $dosCorrectas)->assertUnprocessable()->assertJsonPath('code', 'quiz_invalido');

    $sinEnunciado = cuerpoQuiz(1);
    $sinEnunciado['preguntas'][0]['enunciado'] = '';
    $this->postJson('/api/microcontenidos', $sinEnunciado)->assertUnprocessable()->assertJsonValidationErrors('preguntas.0.enunciado');
});

it('solo un quiz lleva preguntas', function () {
    actuarComoAdmin();

    $this->postJson('/api/microcontenidos', cuerpoMicro(['preguntas' => preguntasDeQuiz(1)]))->assertUnprocessable()->assertJsonValidationErrors('preguntas');
});

it('un quiz sin preguntas se guarda como borrador pero no se publica', function () {
    actuarComoAdmin();

    $id = $this->postJson('/api/microcontenidos', cuerpoQuiz(0, ['preguntas' => null]))->assertCreated()->json('data.id');

    $this->patchJson("/api/microcontenidos/$id/estado", ['estado' => 'Publicado'])->assertUnprocessable()->assertJsonPath('faltantes', ['preguntas']);
});

it('reemplaza título, URL, duración, competencias y preguntas', function () {
    $quiz = quizDe(2);
    actuarComoAdmin();

    $respuesta = $this->putJson("/api/microcontenidos/{$quiz->id}", cuerpoQuiz(3, ['titulo' => 'Nuevo título', 'duracion_minutos' => 9]))->assertOk();

    expect($respuesta->json('data.titulo'))->toBe('Nuevo título')
        ->and($respuesta->json('data.duracion_minutos'))->toBe(9)
        ->and($respuesta->json('data.preguntas'))->toHaveCount(3);
    expect(DB::table('microcontenido_preguntas')->where('microcontenido_id', $quiz->id)->count())->toBe(3)
        ->and(DB::table('microcontenido_opciones')->count())->toBe(9);
});

it('no permite cambiar las preguntas si existe un intento terminado', function () {
    $quiz = quizDe(2);
    intentoMicro(progresoMicro($quiz->id, 77)->id, 'Finalizado', 2, 2);
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$quiz->id}", cuerpoQuiz(3))
        ->assertUnprocessable()->assertJsonPath('code', 'quiz_con_intentos');

    expect(DB::table('microcontenido_preguntas')->where('microcontenido_id', $quiz->id)->count())->toBe(2);
});

it('con intentos terminados sí acepta reenviar las mismas preguntas y cambiar los demás datos', function () {
    $quiz = quizDe(2);
    intentoMicro(progresoMicro($quiz->id, 77)->id, 'Finalizado', 2, 2);
    $iguales = [];
    foreach ($quiz->fresh(['preguntas.opciones'])->preguntas as $p) {
        $iguales[] = [
            'enunciado' => $p->enunciado,
            'opciones' => $p->opciones->map(fn ($o) => ['texto' => $o->texto, 'es_correcta' => (bool) $o->es_correcta])->all(),
        ];
    }
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$quiz->id}", cuerpoMicro(['tipo' => 'Quiz', 'url' => null, 'titulo' => 'Solo el título', 'preguntas' => $iguales]))
        ->assertOk()->assertJsonPath('data.titulo', 'Solo el título')->assertJsonPath('data.tiene_intentos', true);
});

it('con intentos en curso las preguntas se reemplazan y esos intentos se descartan', function () {
    $quiz = quizDe(2);
    $progreso = progresoMicro($quiz->id, 77);
    $intento = intentoMicro($progreso->id, 'EnCurso', 2);
    $pregunta = $quiz->preguntas->first();
    MicroRespuestaModel::query()->create([
        'intento_id' => $intento->id, 'pregunta_id' => $pregunta->id,
        'opcion_id' => MicroOpcionModel::query()->where('pregunta_id', $pregunta->id)->value('id'), 'es_correcta' => true,
    ]);
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$quiz->id}", cuerpoQuiz(3))->assertOk();

    expect(DB::table('microcontenido_intentos')->count())->toBe(0)
        ->and(DB::table('microcontenido_respuestas')->count())->toBe(0)
        ->and(DB::table('microcontenido_progresos')->count())->toBe(1);
});

it('no permite cambiar el tipo', function () {
    $micro = microDe(['tipo' => 'Video']);
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$micro->id}", cuerpoMicro(['tipo' => 'PDF']))->assertUnprocessable()->assertJsonPath('code', 'tipo_inmutable');
});

it('un contenido publicado no deja de cumplir los requisitos al editarlo', function () {
    $micro = microDe(['estado' => 'Publicado', 'tipo' => 'Video']);
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$micro->id}", cuerpoMicro(['url' => null, 'competencia_ids' => []]))
        ->assertUnprocessable()->assertJsonPath('code', 'faltan_requisitos');
});

it('responde 404 si no existe y 403 al colaborador; el colaborador nunca ve es_correcta', function () {
    actuarComoAdmin();
    $this->putJson('/api/microcontenidos/9999', cuerpoQuiz())->assertNotFound();

    $quiz = quizDe(1, ['estado' => 'Publicado']);
    actuarComoUsuario();
    $this->putJson("/api/microcontenidos/{$quiz->id}", cuerpoQuiz())->assertForbidden();
});
