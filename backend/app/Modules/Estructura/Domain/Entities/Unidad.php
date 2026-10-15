<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\UnidadResumida;

/**
 * Unidad organizacional: nodo del árbol de la estructura formal. Tiene estado propio.
 */
final class Unidad
{
    /**
     * @param  array<int, UnidadResumida>  $dependientes  Hijas directas de cualquier estado, por código.
     * @param  string|null  $fechaCreacion  `YYYY-MM-DD`.
     * @param  string|null  $fechaSupresion  `YYYY-MM-DD`; solo mientras está inactiva.
     * @param  string|null  $creadoEn  Fecha y hora UTC (`…Z`).
     * @param  string|null  $modificadoEn  Fecha y hora UTC (`…Z`); nulo hasta la primera edición.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly TipoDeUnidad $tipo,
        public readonly ?UnidadResumida $padre,
        public readonly int $nivel,
        public readonly EstadoUnidad $estado,
        public readonly int $dependientesActivas = 0,
        public readonly int $posicionesVigentes = 0,
        public readonly int $version = 1,
        public readonly ?string $fechaCreacion = null,
        public readonly ?string $fechaSupresion = null,
        public readonly array $dependientes = [],
        public readonly ?int $creadoPor = null,
        public readonly ?string $creadoEn = null,
        public readonly ?int $modificadoPor = null,
        public readonly ?string $modificadoEn = null,
        public readonly bool $tieneDependientes = false,
        public readonly bool $admiteHijas = true,
        public readonly bool $sigueCadena = true,
    ) {}

    public function esRaiz(): bool
    {
        return $this->padre === null;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoUnidad::Activa;
    }
}
