<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoIntento;

/** Intento de un colaborador sobre un Quiz con las respuestas ya dadas. */
final class IntentoQuiz
{
    /**
     * @param  array<int, array{pregunta_id: int, opcion_id: int, es_correcta: bool}>  $respondidas
     */
    public function __construct(
        public readonly int $id,
        public readonly int $progresoId,
        public readonly int $microcontenidoId,
        public readonly int $colaboradorId,
        public readonly EstadoIntento $estado,
        public readonly ?int $puntaje,
        public readonly int $totalPreguntas,
        public readonly array $respondidas,
    ) {}
}
