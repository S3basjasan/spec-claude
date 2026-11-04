<?php

namespace App\Modules\Compensaciones\Domain\Entities;

use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;
use DateTimeImmutable;

/** Una fila del historial de parámetros: nunca se modifica ni se borra. */
final class CambioDeParametro
{
    public function __construct(
        public readonly int $id,
        public readonly ClaveDeParametro $clave,
        public readonly int $valor,
        public readonly string $vigenteDesde,
        public readonly int $creadoPor,
        public readonly DateTimeImmutable $creadoEn,
    ) {}
}
