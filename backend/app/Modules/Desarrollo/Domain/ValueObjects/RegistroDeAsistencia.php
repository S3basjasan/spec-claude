<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Registro guardado de asistencia de un colaborador en una actividad (Pendiente nunca se guarda). */
final class RegistroDeAsistencia
{
    public function __construct(
        public readonly int $colaboradorId,
        public readonly AsistenciaParticipante $asistencia,
        public readonly ?int $calificacion,
        public readonly ?string $observaciones,
    ) {}

    public function igualA(self $otro): bool
    {
        return $this->asistencia === $otro->asistencia
            && $this->calificacion === $otro->calificacion
            && ($this->observaciones ?? '') === ($otro->observaciones ?? '');
    }
}
