<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * No se puede suprimir la posición: está Ocupada o tiene subordinadas vigentes.
 */
final class PosicionNoSuprimible extends RuntimeException
{
    public const OCUPADA = 'ocupada';

    public const SUBORDINADAS = 'subordinadas';

    private function __construct(string $mensaje, public readonly string $motivo, public readonly int $subordinadasVigentes = 0)
    {
        parent::__construct($mensaje);
    }

    public static function ocupada(): self
    {
        return new self('No se puede suprimir la posición: está ocupada.', self::OCUPADA);
    }

    public static function conSubordinadas(int $cantidad): self
    {
        return new self(
            $cantidad === 1
                ? 'No se puede suprimir la posición: tiene 1 subordinada vigente.'
                : "No se puede suprimir la posición: tiene $cantidad subordinadas vigentes.",
            self::SUBORDINADAS,
            $cantidad,
        );
    }
}
