<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use DateTimeImmutable;

/** Evaluación de impacto de una acción Finalizada: una por acción, con quién la creó y quién la modificó por última vez. */
final class ImpactoAccion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $planAccionId,
        public readonly string $indicador,
        public readonly string $unidad,
        public readonly float $valorAntes,
        public readonly float $valorDespues,
        public readonly float $beneficioEstimado,
        public readonly string $justificacion,
        public readonly int $creadoPor,
        public readonly int $actualizadoPor,
        public readonly ?DateTimeImmutable $creadoEn = null,
        public readonly ?DateTimeImmutable $actualizadoEn = null,
    ) {}
}
