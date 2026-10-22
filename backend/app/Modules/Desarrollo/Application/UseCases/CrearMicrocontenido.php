<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ValidadorDeCompetenciasMicro;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeQuiz;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosMicro;

/** Crea un micro-contenido en Borrador (con sus preguntas si es un Quiz) y devuelve su id. */
final class CrearMicrocontenido
{
    public function __construct(
        private readonly MicrocontenidoRepositoryInterface $repositorio,
        private readonly ValidadorDeCompetenciasMicro $competencias,
        private readonly ReglasDeQuiz $quiz,
    ) {}

    public function ejecutar(DatosMicro $datos, int $actorId): int
    {
        if ($datos->tipo->esQuiz() && $datos->preguntas !== null) {
            $this->quiz->validar($datos->preguntas);
        }
        $this->competencias->validarNuevas($datos->competenciaIds);

        return $this->repositorio->crear($datos, $actorId);
    }
}
