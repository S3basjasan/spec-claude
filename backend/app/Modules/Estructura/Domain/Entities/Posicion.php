<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\ValueObjects\EstadoPosicion;
use App\Modules\Estructura\Domain\ValueObjects\Modalidad;

/**
 * Posición: lugar concreto de la estructura con puesto, unidad y supervisor. Los datos del puesto, la unidad y el
 * supervisor son siempre los vigentes (no se copian). El ocupante lo gestiona el módulo de personal.
 */
final class Posicion
{
    /**
     * @param  array{id: int, codigo: string, nombre: string, descripcion: string}  $puesto
     * @param  array{id: int, codigo: string, nombre: string, estado: string, tipo: array{id: int, nombre: string, nivel: int}}  $unidad
     * @param  array{id: int, codigo: string, puesto: string, ocupante_id: int|null}|null  $supervisor  Nulo en la máxima autoridad.
     * @param  array<int, array{id: int, codigo: string, puesto: string, estado: string, ocupante_id: int|null}>  $subordinadas  Directas y vigentes.
     * @param  string|null  $creadoEn  Fecha y hora UTC (`…Z`).
     * @param  string|null  $modificadoEn  Fecha y hora UTC (`…Z`); nulo hasta la primera edición.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly EstadoPosicion $estado,
        public readonly Modalidad $modalidad,
        public readonly array $puesto,
        public readonly array $unidad,
        public readonly ?array $supervisor,
        public readonly ?int $ocupanteId,
        public readonly int $subordinadasVigentes,
        public readonly int $version = 1,
        public readonly array $subordinadas = [],
        public readonly ?int $creadoPor = null,
        public readonly ?string $creadoEn = null,
        public readonly ?int $modificadoPor = null,
        public readonly ?string $modificadoEn = null,
    ) {}

    public function estaSuprimida(): bool
    {
        return $this->estado === EstadoPosicion::Suprimida;
    }

    public function esVigente(): bool
    {
        return $this->estado->enUso();
    }
}
