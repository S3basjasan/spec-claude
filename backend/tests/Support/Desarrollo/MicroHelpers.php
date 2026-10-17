<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroIntentoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Database\Factories\MicroPreguntaFactory;

/**
 * Helpers de la feature 016 (Micro-learning). Datos sintéticos.
 */

/**
 * Micro-contenido con vínculos a competencias (crea una activa si no se indican).
 *
 * @param  array<string, mixed>  $atributos
 * @param  array<int, int>|null  $competenciaIds
 */
function microDe(array $atributos = [], ?array $competenciaIds = null): MicrocontenidoModel
{
    $micro = MicrocontenidoModel::factory()->create($atributos);
    $competenciaIds ??= [CompetenciaModel::factory()->create()->id];

    foreach ($competenciaIds as $id) {
        CompetenciaMicrocontenidoModel::query()->create(['competencia_id' => $id, 'microcontenido_id' => $micro->id]);
    }

    return $micro;
}

/**
 * Quiz con N preguntas de 4 opciones; la correcta de la pregunta i es la opción (i % 4) + 1.
 *
 * @param  array<string, mixed>  $atributos
 */
function quizDe(int $preguntas = 3, array $atributos = [], ?array $competenciaIds = null): MicrocontenidoModel
{
    $micro = microDe(['tipo' => 'Quiz', 'url' => null] + $atributos, $competenciaIds);

    for ($i = 1; $i <= $preguntas; $i++) {
        MicroPreguntaFactory::new()->conOpciones(4, (($i - 1) % 4) + 1)->create(['microcontenido_id' => $micro->id, 'orden' => $i]);
    }

    return $micro->fresh(['preguntas.opciones']);
}

/**
 * Cuerpo válido para crear o reemplazar un micro-contenido.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoMicro(array $cambios = []): array
{
    return array_replace([
        'titulo' => 'Introducción a SQL',
        'tipo' => 'Video',
        'duracion_minutos' => 12,
        'url' => 'https://contenido.test/sql',
        'miniatura_url' => null,
        'competencia_ids' => [CompetenciaModel::factory()->create()->id],
    ], $cambios);
}

/**
 * Preguntas válidas de un quiz (cada una con 3 opciones; la primera es la correcta).
 *
 * @return array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>
 */
function preguntasDeQuiz(int $cantidad = 2): array
{
    $preguntas = [];
    for ($i = 1; $i <= $cantidad; $i++) {
        $preguntas[] = [
            'enunciado' => "¿Pregunta $i?",
            'opciones' => [
                ['texto' => 'Correcta', 'es_correcta' => true],
                ['texto' => 'Incorrecta A', 'es_correcta' => false],
                ['texto' => 'Incorrecta B', 'es_correcta' => false],
            ],
        ];
    }

    return $preguntas;
}

/** Progreso de un colaborador en un micro-contenido. */
function progresoMicro(int $microId, int $colaboradorId, string $estado = 'EnProgreso', int $tenantId = 1, ?int $posicion = null): MicroProgresoModel
{
    return MicroProgresoModel::query()->create([
        'microcontenido_id' => $microId,
        'colaborador_id' => $colaboradorId,
        'tenant_id' => $tenantId,
        'estado' => $estado,
        'posicion_segundos' => $posicion,
        'fecha_inicio' => now()->subDay(),
        'fecha_completado' => $estado === 'Completado' ? now() : null,
    ]);
}

/** Intento de quiz Finalizado (o En curso) para un progreso. */
function intentoMicro(int $progresoId, string $estado = 'Finalizado', int $total = 3, ?int $puntaje = 3): MicroIntentoModel
{
    return MicroIntentoModel::query()->create([
        'progreso_id' => $progresoId,
        'estado' => $estado,
        'puntaje' => $estado === 'Finalizado' ? $puntaje : null,
        'total_preguntas' => $total,
        'finalizado_en' => $estado === 'Finalizado' ? now() : null,
    ]);
}
