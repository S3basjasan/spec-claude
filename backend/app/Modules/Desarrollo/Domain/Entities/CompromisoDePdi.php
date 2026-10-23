<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso;
use DateTimeImmutable;

/** Compromiso de mejora de un plan: una brecha o meta con sus acciones. */
final class CompromisoDePdi
{
    /**
     * @param  list<AccionDePdi>  $acciones
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $orden,
        public readonly DimensionDeCompromiso $dimension,
        public readonly ?string $tipoElemento,
        public readonly ?int $elementoId,
        public readonly ?string $descripcion,
        public readonly ?string $nivelActual,
        public readonly ?string $nivelObjetivo,
        public readonly ?string $indicador,
        public readonly DateTimeImmutable $fechaInicio,
        public readonly DateTimeImmutable $fechaFin,
        public readonly ?ResultadoDeCompromiso $resultado,
        public readonly array $acciones = [],
    ) {}

    /** @param  list<AccionDePdi>  $acciones */
    public function conAcciones(array $acciones): self
    {
        return new self($this->id, $this->orden, $this->dimension, $this->tipoElemento, $this->elementoId, $this->descripcion, $this->nivelActual, $this->nivelObjetivo, $this->indicador, $this->fechaInicio, $this->fechaFin, $this->resultado, $acciones);
    }

    public function conResultado(ResultadoDeCompromiso $resultado): self
    {
        return new self($this->id, $this->orden, $this->dimension, $this->tipoElemento, $this->elementoId, $this->descripcion, $this->nivelActual, $this->nivelObjetivo, $this->indicador, $this->fechaInicio, $this->fechaFin, $resultado, $this->acciones);
    }

    /** Clave del elemento para detectar compromisos repetidos (FR-015); nula en Experiencia y Meta. */
    public function claveDeElemento(): ?string
    {
        return $this->elementoId === null ? null : $this->dimension->value.':'.$this->elementoId;
    }
}
