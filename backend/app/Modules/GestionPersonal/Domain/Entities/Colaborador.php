<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

final class Colaborador
{
    /** @param array<string, mixed> $datos Campos editables (obligatorios y opcionales). */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly string $codigo,
        public readonly int $version,
        public readonly array $datos,
        public readonly int $createdBy,
        public readonly int $updatedBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
