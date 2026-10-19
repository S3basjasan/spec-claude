<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use DateTimeImmutable;

/** Respuesta de un colaborador a un hito (30, 60 o 90 días) de una actividad. Inmutable: nunca se modifica ni se elimina. */
final class SeguimientoImpacto
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $actividadId,
        public readonly int $colaboradorId,
        public readonly int $hito,
        public readonly int $aplicacion,
        public readonly int $apoyo,
        public readonly ?string $comentario,
        public readonly ?DateTimeImmutable $creadoEn = null,
    ) {}
}
