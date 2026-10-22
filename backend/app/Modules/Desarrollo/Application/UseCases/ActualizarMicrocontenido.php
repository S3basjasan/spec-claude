<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ValidadorDeCompetenciasMicro;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\QuizConIntentos;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeMicrocontenido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeQuiz;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;

/**
 * Reemplaza los datos editables de un micro-contenido (título, URL, miniatura, duración, competencias y, si es un
 * Quiz sin intentos terminados, sus preguntas). El tipo, el código y el estado no se editan aquí.
 */
final class ActualizarMicrocontenido
{
    public function __construct(
        private readonly MicrocontenidoRepositoryInterface $repositorio,
        private readonly ValidadorDeCompetenciasMicro $competencias,
        private readonly ReglasDeMicrocontenido $reglas,
        private readonly ReglasDeQuiz $quiz,
    ) {}

    public function ejecutar(int $id, DatosMicro $datos, int $actorId): void
    {
        $actual = $this->repositorio->obtener($id) ?? throw new MicroNoEncontrado;

        $this->reglas->validarTipoInmutable($actual->tipo, $datos->tipo);

        $preguntas = $datos->preguntas;
        if ($actual->tipo->esQuiz() && $preguntas !== null) {
            $this->quiz->validar($preguntas, $actual->estado === EstadoMicro::Publicado);

            if ($this->quiz->iguales($this->comoEntrada($actual->preguntas), $preguntas)) {
                $preguntas = null; // sin cambios: no se tocan las preguntas ni se descartan intentos
            } elseif ($actual->tieneIntentos) {
                throw new QuizConIntentos;
            }
        }

        $this->competencias->validarNuevas($datos->competenciaIds, $actual->competenciaIds);

        if ($actual->estado === EstadoMicro::Publicado) {
            $this->reglas->validarPublicable(
                $datos->titulo,
                $datos->duracionMinutos,
                $actual->tipo,
                $datos->url,
                $preguntas === null ? count($actual->preguntas) : count($preguntas),
                $this->competencias->contarActivas($datos->competenciaIds),
            );
        }

        $this->repositorio->actualizar($id, new DatosMicro(
            $datos->titulo,
            $actual->tipo,
            $datos->duracionMinutos,
            $datos->url,
            $datos->miniaturaUrl,
            $datos->competenciaIds,
            $preguntas,
        ), $actorId);
    }

    /**
     * @param  array<int, array{id: int, enunciado: string, orden: int, opciones: array<int, array{id: int, texto: string, orden: int, es_correcta: bool}>}>  $preguntas
     * @return array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>
     */
    private function comoEntrada(array $preguntas): array
    {
        return array_map(fn (array $p) => [
            'enunciado' => $p['enunciado'],
            'opciones' => array_map(fn (array $o) => ['texto' => $o['texto'], 'es_correcta' => $o['es_correcta']], $p['opciones']),
        ], $preguntas);
    }
}
