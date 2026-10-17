<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Una línea del historial: valor anterior y nuevo de la asistencia y la calificación (nulo = Pendiente / sin nota). */
final class CambioDeAsistencia
{
    public function __construct(
        public readonly int $colaboradorId,
        public readonly ?AsistenciaParticipante $asistenciaAnterior,
        public readonly ?AsistenciaParticipante $asistenciaNueva,
        public readonly ?int $calificacionAnterior,
        public readonly ?int $calificacionNueva,
    ) {}
}
