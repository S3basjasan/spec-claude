<?php

namespace App\Modules\Compensaciones\Application\DTOs;

/**
 * Un parámetro tal como lo ve el Gestor.
 *
 * @phpstan-type Programado array{valor: int, vigente_desde: string}
 * @phpstan-type UltimoCambio array{por: string|null, en: string, valor_anterior: int|null, valor_nuevo: int}
 */
final class ParametroDTO
{
    /**
     * @param  array{valor: int, vigente_desde: string}|null  $valorProgramado
     * @param  array{por: string|null, en: string, valor_anterior: int|null, valor_nuevo: int}|null  $ultimoCambio
     */
    public function __construct(
        public readonly string $clave,
        public readonly int $valor,
        public readonly ?array $valorProgramado,
        public readonly int $minimo,
        public readonly int $maximo,
        public readonly int $porDefecto,
        public readonly int $version,
        public readonly ?array $ultimoCambio,
    ) {}
}
