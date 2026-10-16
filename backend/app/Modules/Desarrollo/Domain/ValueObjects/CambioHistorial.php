<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Una entrada del historial formativo (solo inserción). */
final class CambioHistorial
{
    public function __construct(
        public readonly ?int $competenciaId,
        public readonly TipoCambioHistorial $tipoCambio,
        public readonly AccionHistorial $accion,
        public readonly ?int $recursoId,
        public readonly ?string $valorAnterior,
        public readonly ?string $valorNuevo,
    ) {}
}
