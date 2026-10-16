<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

final class Movilidad
{
    public function __construct(
        public readonly int $colaboradorId,
        public readonly string $estado,
        public readonly ?string $observaciones,
        public readonly int $version,
        public readonly ?string $actualizadaAt,
        public readonly ?int $actualizadaPor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return ['estado' => $this->estado, 'observaciones' => $this->observaciones];
    }
}
