<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Unidad que quedaría bajo una unidad padre de nivel igual o más profundo tras un reordenamiento, con los tipos y
 * niveles resultantes de ella y de su padre.
 */
final class UnidadAfectadaPorCadena
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $tipoNombre,
        public readonly int $tipoNivel,
        public readonly int $padreId,
        public readonly string $padreCodigo,
        public readonly string $padreNombre,
        public readonly string $padreTipoNombre,
        public readonly int $padreTipoNivel,
    ) {}
}
