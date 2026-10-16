<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

final class ExperienciaAnterior
{
    public function __construct(
        public readonly int $id,
        public readonly int $colaboradorId,
        public readonly string $organizacion,
        public readonly string $cargo,
        public readonly string $fechaInicio,
        public readonly ?string $fechaFinalizacion,
        public readonly ?string $funciones,
        public readonly ?string $observaciones,
        public readonly int $version,
        public readonly int $createdBy,
        public readonly int $updatedBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Campos editables como los espera ReglasDeDesarrollo.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'organizacion' => $this->organizacion,
            'cargo' => $this->cargo,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_finalizacion' => $this->fechaFinalizacion,
            'funciones' => $this->funciones,
            'observaciones' => $this->observaciones,
        ];
    }
}
