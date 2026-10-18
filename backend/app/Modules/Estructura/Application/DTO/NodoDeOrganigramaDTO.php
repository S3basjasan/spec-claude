<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Posicion;

/**
 * Posición vigente tal como la dibuja el organigrama: todo lo que necesitan la tarjeta y el panel de detalle, para que el
 * panel se abra sin otra petición. No incluye auditoría.
 */
final class NodoDeOrganigramaDTO
{
    /**
     * @param  array{id: int, codigo: string, nombre: string, descripcion: string}  $puesto
     * @param  array{id: int, codigo: string, nombre: string, tipo: array{id: int, nombre: string, nivel: int}, estado: string}  $unidad
     * @param  array{id: int, nombre: string|null}|null  $ocupante
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $estado,
        public readonly string $modalidad,
        public readonly ?int $posicionJefeId,
        public readonly array $puesto,
        public readonly array $unidad,
        public readonly ?array $ocupante,
    ) {}

    /**
     * @param  array<int, string>  $nombres  id de usuario → nombre
     */
    public static function desde(Posicion $posicion, array $nombres): self
    {
        return new self(
            $posicion->id,
            $posicion->codigo,
            $posicion->estado->value,
            $posicion->modalidad->value,
            $posicion->supervisor['id'] ?? null,
            $posicion->puesto,
            [
                'id' => $posicion->unidad['id'],
                'codigo' => $posicion->unidad['codigo'],
                'nombre' => $posicion->unidad['nombre'],
                'tipo' => $posicion->unidad['tipo'],
                'estado' => $posicion->unidad['estado'],
            ],
            $posicion->ocupanteId === null ? null : [
                'id' => $posicion->ocupanteId,
                'nombre' => $nombres[$posicion->ocupanteId] ?? null,
            ],
        );
    }
}
