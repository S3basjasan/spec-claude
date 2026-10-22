<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use DateTimeImmutable;

/** Acción de administración registrada (solo inserción). */
final class AccionDePolitica
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $politicaId,
        public readonly int $versionId,
        public readonly string $accion,
        public readonly int $actorId,
        public readonly ?string $motivo,
        public readonly ?string $operacionId,
        public readonly ?string $operacionHash,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
