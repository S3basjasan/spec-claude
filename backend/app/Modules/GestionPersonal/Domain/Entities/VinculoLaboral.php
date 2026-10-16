<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use App\Modules\GestionPersonal\Domain\Services\PeriodoLaboral;

final class VinculoLaboral
{
    /** @param list<AsignacionLaboral> $asignaciones */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $colaboradorId,
        public readonly string $fechaIngreso,
        public readonly ?string $fechaSalida,
        public readonly string $tipoContratoClave,
        public readonly int $version,
        public readonly int $createdBy,
        public readonly string $createdAt,
        public readonly array $asignaciones = [],
    ) {}

    public function periodo(): PeriodoLaboral
    {
        return new PeriodoLaboral($this->fechaIngreso, $this->fechaSalida);
    }

    /** Asignación vigente en la fecha, si la hay. */
    public function asignacionEn(string $fecha): ?AsignacionLaboral
    {
        foreach ($this->asignaciones as $asignacion) {
            if ($asignacion->periodo()->contiene($fecha)) {
                return $asignacion;
            }
        }

        return null;
    }

    /** Última asignación por fecha de inicio (la del ingreso, mientras no existan movimientos). */
    public function asignacionPrincipal(): ?AsignacionLaboral
    {
        $principal = null;
        foreach ($this->asignaciones as $asignacion) {
            if ($principal === null || $asignacion->fechaInicio > $principal->fechaInicio) {
                $principal = $asignacion;
            }
        }

        return $principal;
    }
}
