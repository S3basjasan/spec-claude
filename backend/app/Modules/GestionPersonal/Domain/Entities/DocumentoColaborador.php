<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

final class DocumentoColaborador
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $colaboradorId,
        public readonly int $categoriaId,
        public readonly string $titulo,
        /** Clave interna del objeto: nunca sale del módulo. */
        public readonly string $objectKey,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $sha256,
        public readonly bool $visibleColaborador,
        public readonly string $estado,
        public readonly int $version,
        public readonly int $createdBy,
        public readonly int $updatedBy,
        public readonly ?int $archivedBy,
        public readonly ?string $archivedAt,
        public readonly string $operacionHash,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function estaDisponible(): bool
    {
        return $this->estado === 'Disponible';
    }
}
