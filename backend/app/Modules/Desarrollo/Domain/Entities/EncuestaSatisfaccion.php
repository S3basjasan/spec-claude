<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use DateTimeImmutable;

/** Respuesta de un colaborador a la encuesta de satisfacción de una actividad. Inmutable: nunca se modifica ni se elimina. */
final class EncuestaSatisfaccion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $actividadId,
        public readonly int $colaboradorId,
        public readonly int $calificacion,
        public readonly ?string $comentario,
        public readonly ?DateTimeImmutable $creadoEn = null,
    ) {}
}
