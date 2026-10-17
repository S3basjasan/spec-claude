<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoProgresoMicro;

/** Registro de progreso de un colaborador en un micro-contenido. */
final class ProgresoMicro
{
    public function __construct(
        public readonly int $id,
        public readonly int $microcontenidoId,
        public readonly int $colaboradorId,
        public readonly int $tenantId,
        public readonly EstadoProgresoMicro $estado,
        public readonly ?int $posicionSegundos,
        public readonly string $fechaInicio,
        public readonly ?string $fechaCompletado,
    ) {}

    public function estaCompletado(): bool
    {
        return $this->estado === EstadoProgresoMicro::Completado;
    }
}
