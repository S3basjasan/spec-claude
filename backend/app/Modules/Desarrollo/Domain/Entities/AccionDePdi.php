<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use DateTimeImmutable;

/**
 * Acción de un compromiso. Con vínculo (`planAccionId`) el estado se deriva del Plan de Formación mientras el plan no
 * esté Cerrado; al cerrar se guarda el estado final y `estadoCongelado` pasa a verdadero (FR-020a).
 */
final class AccionDePdi
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $orden,
        public readonly TipoDeAccionPdi $tipo,
        public readonly string $descripcion,
        public readonly ?string $personaCargo,
        public readonly DateTimeImmutable $fechaInicio,
        public readonly DateTimeImmutable $fechaFin,
        public readonly ?int $planAccionId,
        public readonly EstadoDeAccionPdi $estado,
        public readonly bool $estadoCongelado = false,
    ) {}

    public function estaVinculada(): bool
    {
        return $this->planAccionId !== null;
    }

    public function conEstado(EstadoDeAccionPdi $estado, bool $congelado = false): self
    {
        return new self($this->id, $this->orden, $this->tipo, $this->descripcion, $this->personaCargo, $this->fechaInicio, $this->fechaFin, $this->planAccionId, $estado, $congelado);
    }

    public function conVinculo(?int $planAccionId): self
    {
        return new self($this->id, $this->orden, $this->tipo, $this->descripcion, $this->personaCargo, $this->fechaInicio, $this->fechaFin, $planAccionId, EstadoDeAccionPdi::Pendiente, false);
    }
}
