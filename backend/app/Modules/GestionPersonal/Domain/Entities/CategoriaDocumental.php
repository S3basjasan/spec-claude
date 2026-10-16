<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

final class CategoriaDocumental
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly string $nombre,
        public readonly string $estado,
        public readonly int $version,
        public readonly int $createdBy,
        public readonly int $updatedBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
