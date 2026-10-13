<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Puesto;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;

final class PuestoDTO
{
    /**
     * @param  array<int, array{orden: int, descripcion: string, tipo: string}>  $funciones  Vacío en el listado.
     * @param  array<int, array{tipo: string, descripcion: string}>  $requisitos  Vacío en el listado.
     * @param  array<int, array{competencia_id: int, codigo: string, nombre: string, tipo: string, estado: string, grado_requerido: string}>  $competencias  Vacío en el listado.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $mision,
        public readonly string $estado,
        public readonly int $version,
        public readonly array $funciones = [],
        public readonly array $requisitos = [],
        public readonly array $competencias = [],
    ) {}

    public static function desde(Puesto $puesto): self
    {
        return new self(
            $puesto->id,
            $puesto->codigo,
            $puesto->nombre,
            $puesto->descripcion,
            $puesto->mision,
            $puesto->estado->value,
            $puesto->version,
            array_map(fn (FuncionPuesto $f) => [
                'orden' => $f->orden,
                'descripcion' => $f->descripcion,
                'tipo' => $f->tipo->value,
            ], $puesto->funciones),
            array_map(fn (RequisitoPuesto $r) => [
                'tipo' => $r->tipo->value,
                'descripcion' => $r->descripcion,
            ], $puesto->requisitos),
            array_map(fn (CompetenciaDePuesto $c) => [
                'competencia_id' => $c->competencia->id,
                'codigo' => $c->competencia->codigo,
                'nombre' => $c->competencia->nombre,
                'tipo' => $c->competencia->tipo->value,
                'estado' => $c->competencia->estado->value,
                'grado_requerido' => $c->gradoRequerido,
            ], $puesto->competencias),
        );
    }
}
