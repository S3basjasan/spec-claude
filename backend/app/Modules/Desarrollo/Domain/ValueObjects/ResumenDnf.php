<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use DateTimeImmutable;

/** Cabecera de una DNF para el historial: sin detalle de brechas. */
final class ResumenDnf
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly DateTimeImmutable $fecha,
        public readonly AlcanceDnf $alcance,
        public readonly ?int $departamentoId,
        public readonly ?int $colaboradorId,
        public readonly int $colaboradores,
        public readonly int $cantidadBrechas,
        public readonly ?PrioridadBrecha $prioridadMasAlta,
        public readonly bool $tienePlan,
    ) {}
}
