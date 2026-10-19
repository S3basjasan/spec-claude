<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\SeguimientoNoPermitido;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\HitoSeguimiento;
use DateTimeImmutable;

/**
 * Cuándo puede un colaborador responder el seguimiento a 30, 60 o 90 días: actividad Finalizada, asistencia Presente,
 * desde el vencimiento del hito (fecha de fin + días) y durante 15 días corridos (inclusive), una sola vez por hito.
 */
final class ReglasDeSeguimiento
{
    /** @return string|null motivo estable por el que no puede responder, o nulo si puede */
    public function motivoDeRechazo(int $hito, EstadoAccion $estado, AsistenciaParticipante $asistencia, DateTimeImmutable $fechaFin, DateTimeImmutable $hoy, bool $yaRespondio): ?string
    {
        $h = HitoSeguimiento::tryFrom($hito);
        if ($h === null) {
            return SeguimientoNoPermitido::HITO_INVALIDO;
        }
        if ($estado !== EstadoAccion::Finalizada) {
            return SeguimientoNoPermitido::ACTIVIDAD_NO_FINALIZADA;
        }
        if ($asistencia !== AsistenciaParticipante::Presente) {
            return SeguimientoNoPermitido::SIN_ASISTENCIA;
        }
        if ($yaRespondio) {
            return SeguimientoNoPermitido::YA_RESPONDIDA;
        }
        $hoy = $hoy->setTime(0, 0);
        if ($hoy < $h->vencimiento($fechaFin)) {
            return SeguimientoNoPermitido::HITO_NO_VENCIDO;
        }
        if ($hoy > $h->limiteDeRespuesta($fechaFin)) {
            return SeguimientoNoPermitido::VENTANA_CERRADA;
        }

        return null;
    }

    public function validar(int $hito, EstadoAccion $estado, AsistenciaParticipante $asistencia, DateTimeImmutable $fechaFin, DateTimeImmutable $hoy, bool $yaRespondio): void
    {
        $motivo = $this->motivoDeRechazo($hito, $estado, $asistencia, $fechaFin, $hoy, $yaRespondio);
        if ($motivo !== null) {
            throw new SeguimientoNoPermitido($motivo);
        }
    }

    /**
     * Hitos que el colaborador puede responder hoy en una actividad Finalizada en la que estuvo Presente.
     *
     * @param  list<int>  $hitosRespondidos
     * @return list<HitoSeguimiento>
     */
    public function pendientes(DateTimeImmutable $fechaFin, DateTimeImmutable $hoy, array $hitosRespondidos): array
    {
        $hoy = $hoy->setTime(0, 0);
        $pendientes = [];
        foreach (HitoSeguimiento::cases() as $hito) {
            if (in_array($hito->value, $hitosRespondidos, true)) {
                continue;
            }
            if ($hoy >= $hito->vencimiento($fechaFin) && $hoy <= $hito->limiteDeRespuesta($fechaFin)) {
                $pendientes[] = $hito;
            }
        }

        return $pendientes;
    }
}
