<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * Datos de una acción del plan vistos desde Actividades. Siempre se leen del plan vigente (no se copian).
 * Vive en el Dominio para que las reglas de la actividad no dependan de la capa de aplicación.
 */
final class AccionDeActividad
{
    /**
     * @param  list<int>  $competenciaIds
     * @param  list<int>  $colaboradorIds  inscritos actuales de la acción
     */
    public function __construct(
        public readonly int $accionId,
        public readonly int $planId,
        public readonly string $planCodigo,
        public readonly string $planNombre,
        public readonly EstadoPlan $planEstado,
        public readonly string $nombre,
        public readonly ?TipoAccion $tipo,
        public readonly ?DateTimeImmutable $fechaInicio,
        public readonly ?DateTimeImmutable $fechaFin,
        public readonly ?float $duracionHoras,
        public readonly ?string $formador,
        public readonly ?int $capacidadMaxima,
        public readonly bool $cancelada,
        public readonly array $competenciaIds,
        public readonly array $colaboradorIds,
    ) {}

    public function inscritos(): int
    {
        return count($this->colaboradorIds);
    }
}
