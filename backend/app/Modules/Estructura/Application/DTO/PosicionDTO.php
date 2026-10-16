<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Posicion;

/**
 * Posición del listado: datos vigentes de su puesto, su unidad, su supervisor y su ocupante, y cuántas subordinadas
 * vigentes tiene.
 */
class PosicionDTO
{
    /**
     * @param  array<string, mixed>  $puesto
     * @param  array<string, mixed>  $unidad
     * @param  array<string, mixed>|null  $supervisor
     * @param  array{id: int, nombre: string|null}|null  $ocupante
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $estado,
        public readonly string $modalidad,
        public readonly array $puesto,
        public readonly array $unidad,
        public readonly ?array $supervisor,
        public readonly ?array $ocupante,
        public readonly int $subordinadasVigentes,
    ) {}

    /**
     * @param  array<int, string>  $nombres  id de usuario → nombre (de los ocupantes de la página)
     */
    public static function desde(Posicion $posicion, array $nombres = []): self
    {
        return new self(...self::campos($posicion, $nombres));
    }

    /**
     * @param  array<int, string>  $nombres
     * @return array<string, mixed>
     */
    protected static function campos(Posicion $posicion, array $nombres = []): array
    {
        return [
            'id' => $posicion->id,
            'codigo' => $posicion->codigo,
            'estado' => $posicion->estado->value,
            'modalidad' => $posicion->modalidad->value,
            'puesto' => [
                'id' => $posicion->puesto['id'],
                'codigo' => $posicion->puesto['codigo'],
                'nombre' => $posicion->puesto['nombre'],
            ],
            'unidad' => [
                'id' => $posicion->unidad['id'],
                'codigo' => $posicion->unidad['codigo'],
                'nombre' => $posicion->unidad['nombre'],
                'estado' => $posicion->unidad['estado'],
            ],
            'supervisor' => $posicion->supervisor === null ? null : [
                'id' => $posicion->supervisor['id'],
                'codigo' => $posicion->supervisor['codigo'],
                'nombre' => $posicion->supervisor['puesto'],
            ],
            'ocupante' => $posicion->ocupanteId === null ? null : [
                'id' => $posicion->ocupanteId,
                'nombre' => $nombres[$posicion->ocupanteId] ?? null,
            ],
            'subordinadasVigentes' => $posicion->subordinadasVigentes,
        ];
    }
}
