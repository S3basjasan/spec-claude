<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

final class FormacionAcademica
{
    public function __construct(
        public readonly int $id,
        public readonly int $colaboradorId,
        public readonly string $nivel,
        public readonly string $tituloPrograma,
        public readonly string $institucion,
        public readonly string $estado,
        public readonly ?string $fechaInicio,
        public readonly ?string $fechaFinalizacion,
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
            'nivel' => $this->nivel,
            'titulo_programa' => $this->tituloPrograma,
            'institucion' => $this->institucion,
            'estado' => $this->estado,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_finalizacion' => $this->fechaFinalizacion,
            'observaciones' => $this->observaciones,
        ];
    }
}
