<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Nombre de una ruta: se recorta y se colapsan los espacios; la unicidad usa la clave en minúsculas. */
final class NombreDeRuta
{
    public const MAXIMO = 80;

    public readonly string $valor;

    public function __construct(string $nombre)
    {
        $this->valor = trim((string) preg_replace('/\s+/u', ' ', $nombre));
    }

    public function clave(): string
    {
        return mb_strtolower($this->valor);
    }

    public function esVacio(): bool
    {
        return $this->valor === '';
    }

    public function excedeElMaximo(): bool
    {
        return mb_strlen($this->valor) > self::MAXIMO;
    }
}
