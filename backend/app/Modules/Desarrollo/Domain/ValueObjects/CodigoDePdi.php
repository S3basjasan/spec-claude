<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use InvalidArgumentException;

/** Código único y secuencial de un plan: PDI-0001, PDI-0002… */
final class CodigoDePdi
{
    private function __construct(public readonly int $numero) {}

    public static function desdeNumero(int $numero): self
    {
        if ($numero < 1) {
            throw new InvalidArgumentException('El número de plan debe ser mayor que cero.');
        }

        return new self($numero);
    }

    public static function desdeTexto(string $codigo): self
    {
        if (preg_match('/^PDI-(\d{4,})$/', $codigo, $m) !== 1) {
            throw new InvalidArgumentException('El código de plan no tiene el formato PDI-0001.');
        }

        return self::desdeNumero((int) $m[1]);
    }

    public function valor(): string
    {
        return sprintf('PDI-%04d', $this->numero);
    }

    public function siguiente(): self
    {
        return new self($this->numero + 1);
    }
}
