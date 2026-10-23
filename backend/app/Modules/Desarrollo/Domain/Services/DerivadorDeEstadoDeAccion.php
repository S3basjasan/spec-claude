<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use DateTimeImmutable;

/**
 * Estado de una acción del PDI vinculada a una acción del Plan de Formación (FR-020):
 *  - acción del plan cancelada o actividad Cancelada ⇒ Cancelada;
 *  - actividad Finalizada con la asistencia del colaborador (Presente) ⇒ Cumplida;
 *  - en otro caso, En curso si hoy está dentro de las fechas de la acción del PDI y Pendiente si no.
 */
final class DerivadorDeEstadoDeAccion
{
    public function derivar(
        bool $accionCancelada,
        ?string $estadoDeLaActividad,
        ?string $asistencia,
        DateTimeImmutable $hoy,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fin,
    ): EstadoDeAccionPdi {
        if ($accionCancelada || $estadoDeLaActividad === 'Cancelada') {
            return EstadoDeAccionPdi::Cancelada;
        }
        if ($estadoDeLaActividad === 'Finalizada' && $asistencia === 'Presente') {
            return EstadoDeAccionPdi::Cumplida;
        }

        $dia = $hoy->format('Y-m-d');

        return $dia >= $inicio->format('Y-m-d') && $dia <= $fin->format('Y-m-d') ? EstadoDeAccionPdi::EnCurso : EstadoDeAccionPdi::Pendiente;
    }
}
