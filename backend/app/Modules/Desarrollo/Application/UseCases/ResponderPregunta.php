<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Domain\Exceptions\IntentoDescartado;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoDisponible;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\RespuestaInvalida;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIntento;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoIntento;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use Illuminate\Support\Facades\DB;

/**
 * Registra la respuesta de una pregunta e informa si fue correcta y cuál era la opción correcta. Al responder la
 * última pregunta finaliza el intento, calcula el puntaje y completa el progreso (sin revertirlo nunca).
 */
final class ResponderPregunta
{
    public function __construct(
        private readonly AccesoDeColaborador $acceso,
        private readonly MicrocontenidoRepositoryInterface $contenidos,
        private readonly ProgresoMicroRepositoryInterface $progresos,
        private readonly CalculadorDeIntento $calculador,
    ) {}

    /**
     * @return array{es_correcta: bool, opcion_correcta_id: int|null, estado: string, respondidas: array<int, array{pregunta_id: int, opcion_id: int, es_correcta: bool}>, puntaje: int|null, total_preguntas: int, completado_ahora: bool}
     */
    public function ejecutar(int $intentoId, int $usuarioId, int $preguntaId, int $opcionId): array
    {
        $cuenta = $this->acceso->cuenta($usuarioId);
        $intento = $this->progresos->obtenerIntento($intentoId);
        if ($intento === null || $intento->colaboradorId !== $cuenta['colaborador_id']) {
            throw new MicroNoEncontrado('El intento no existe.');
        }
        if ($intento->estado === EstadoIntento::Finalizado) {
            throw new IntentoDescartado;
        }

        $micro = $this->contenidos->obtener($intento->microcontenidoId) ?? throw new MicroNoEncontrado;
        if ($micro->estado !== EstadoMicro::Publicado) {
            throw new MicroNoDisponible;
        }

        $pregunta = null;
        foreach ($micro->preguntas as $p) {
            if ($p['id'] === $preguntaId) {
                $pregunta = $p;
            }
        }
        if ($pregunta === null) {
            throw new RespuestaInvalida('La pregunta no pertenece a este quiz.', 'pregunta_id');
        }
        if (in_array($preguntaId, array_column($intento->respondidas, 'pregunta_id'), true)) {
            throw new RespuestaInvalida('Ya respondiste esta pregunta.', 'pregunta_id');
        }

        $opcion = null;
        foreach ($pregunta['opciones'] as $o) {
            if ($o['id'] === $opcionId) {
                $opcion = $o;
            }
        }
        if ($opcion === null) {
            throw new RespuestaInvalida('La opción no pertenece a la pregunta.', 'opcion_id');
        }

        return DB::transaction(function () use ($intento, $pregunta, $opcion, $preguntaId, $opcionId) {
            $this->progresos->registrarRespuesta($intento->id, $preguntaId, $opcionId, $opcion['es_correcta']);

            $respondidas = [...$intento->respondidas, ['pregunta_id' => $preguntaId, 'opcion_id' => $opcionId, 'es_correcta' => $opcion['es_correcta']]];
            $terminado = $this->calculador->terminado(count($respondidas), $intento->totalPreguntas);
            $puntaje = null;
            $completadoAhora = false;

            if ($terminado) {
                $puntaje = $this->calculador->puntaje($respondidas);
                $this->progresos->finalizarIntento($intento->id, $puntaje);

                $progreso = $this->progresos->progresoDe($intento->microcontenidoId, $intento->colaboradorId);
                if ($progreso !== null && ! $progreso->estaCompletado()) {
                    $this->progresos->completar($progreso->id);
                    $completadoAhora = true;
                }
            }

            return [
                'es_correcta' => $opcion['es_correcta'],
                'opcion_correcta_id' => $this->calculador->opcionCorrectaId($pregunta['opciones']),
                'estado' => $terminado ? EstadoIntento::Finalizado->value : EstadoIntento::EnCurso->value,
                'respondidas' => $respondidas,
                'puntaje' => $puntaje,
                'total_preguntas' => $intento->totalPreguntas,
                'completado_ahora' => $completadoAhora,
            ];
        });
    }
}
