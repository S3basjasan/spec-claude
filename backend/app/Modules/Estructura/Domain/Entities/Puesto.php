<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;

/**
 * Cargo descrito con independencia de la persona que lo ocupa. Nunca se elimina: se activa y desactiva.
 */
final class Puesto
{
    /**
     * @param  array<int, FuncionPuesto>  $funciones  Por `orden` (vacío en el listado).
     * @param  array<int, RequisitoPuesto>  $requisitos  Por tipo y luego por creación (vacío en el listado).
     * @param  array<int, CompetenciaDePuesto>  $competencias  Por nombre de la competencia (vacío en el listado).
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $mision,
        public readonly EstadoPuesto $estado,
        public readonly int $version,
        public readonly ?int $creadoPor = null,
        public readonly ?int $modificadoPor = null,
        public readonly array $funciones = [],
        public readonly array $requisitos = [],
        public readonly array $competencias = [],
    ) {}

    public function activo(): bool
    {
        return $this->estado === EstadoPuesto::Activo;
    }

    /** Estado completo del puesto, para compararlo en la auditoría. */
    public function instantanea(): InstantaneaDePuesto
    {
        return new InstantaneaDePuesto(
            $this->codigo,
            $this->nombre,
            $this->descripcion,
            $this->mision,
            $this->estado,
            $this->funciones,
            $this->requisitos,
            $this->competencias,
        );
    }
}
