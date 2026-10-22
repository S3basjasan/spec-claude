<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use InvalidArgumentException;

/** Código único y secuencial de una adecuación: ADE-0001, ADE-0002… */
final class CodigoDeAdecuacion
{
    private function __construct(public readonly int $numero) {}

    public static function desdeNumero(int $numero): self
    {
        if ($numero < 1) {
            throw new InvalidArgumentException('El número de adecuación debe ser mayor que cero.');
        }

        return new self($numero);
    }

    public static function desdeTexto(string $codigo): self
    {
        if (preg_match('/^ADE-(\d{4,})$/', $codigo, $m) !== 1) {
            throw new InvalidArgumentException('El código de adecuación no tiene el formato ADE-0001.');
        }

        return self::desdeNumero((int) $m[1]);
    }

    public function valor(): string
    {
        return sprintf('ADE-%04d', $this->numero);
    }

    public function siguiente(): self
    {
        return new self($this->numero + 1);
    }
}
