<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Escala con la que se nombran los cuatro grados de una competencia (Letras, Números o Categorías).
 * El orden 1 es el grado más alto.
 */
final class EscalaDeGrados
{
    public const LETRAS = 'Letras';

    public const CATEGORIAS = 'Categorías';

    /**
     * @param  array<int, string>  $valores  Valor por orden (1 a 4).
     */
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly bool $activa,
        public readonly array $valores,
    ) {}

    public function esLetras(): bool
    {
        return $this->nombre === self::LETRAS;
    }

    public function esCategorias(): bool
    {
        return $this->nombre === self::CATEGORIAS;
    }

    /**
     * Etiqueta del grado: en Letras `Grado A`, en Números el valor y en Categorías el nombre propio de la
     * competencia si lo tiene (o el valor por defecto del catálogo).
     *
     * @param  array<int, string>|null  $categorias  Nombres propios por orden (1 a 4) o null.
     */
    public function etiqueta(int $orden, ?array $categorias = null): string
    {
        $valor = $this->valores[$orden] ?? (string) $orden;

        return match (true) {
            $this->esLetras() => 'Grado '.$valor,
            $this->esCategorias() && $categorias !== null && isset($categorias[$orden]) => $categorias[$orden],
            default => $valor,
        };
    }
}
