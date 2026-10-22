<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\EncuestaNoPermitida;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use DateTimeImmutable;

/**
 * Cuándo puede un colaborador responder la encuesta de satisfacción: actividad Finalizada, asistencia Presente,
 * dentro de los 30 días corridos posteriores a su fecha de fin y una sola vez. No hay fecha de activación.
 */
final class ReglasDeEncuesta
{
    public const VENTANA_DIAS = 30;

    /** Último día (inclusive) en que se acepta la respuesta. */
    public function vence(DateTimeImmutable $fechaFin): DateTimeImmutable
    {
        return $fechaFin->setTime(0, 0)->modify('+'.self::VENTANA_DIAS.' days');
    }

    /** @return string|null motivo estable por el que no puede responder, o nulo si puede */
    public function motivoDeRechazo(EstadoAccion $estado, AsistenciaParticipante $asistencia, DateTimeImmutable $fechaFin, DateTimeImmutable $hoy, bool $yaRespondio): ?string
    {
        if ($estado !== EstadoAccion::Finalizada) {
            return EncuestaNoPermitida::ACTIVIDAD_NO_FINALIZADA;
        }
        if ($asistencia !== AsistenciaParticipante::Presente) {
            return EncuestaNoPermitida::SIN_ASISTENCIA;
        }
        if ($yaRespondio) {
            return EncuestaNoPermitida::YA_RESPONDIDA;
        }
        if ($hoy->setTime(0, 0) > $this->vence($fechaFin)) {
            return EncuestaNoPermitida::VENTANA_CERRADA;
        }

        return null;
    }

    public function validar(EstadoAccion $estado, AsistenciaParticipante $asistencia, DateTimeImmutable $fechaFin, DateTimeImmutable $hoy, bool $yaRespondio): void
    {
        $motivo = $this->motivoDeRechazo($estado, $asistencia, $fechaFin, $hoy, $yaRespondio);
        if ($motivo !== null) {
            throw new EncuestaNoPermitida($motivo);
        }
    }
}
