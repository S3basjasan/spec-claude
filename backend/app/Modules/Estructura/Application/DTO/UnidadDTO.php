<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Unidad;

/**
 * Unidad tal como aparece en el listado: datos propios, tipo con su nivel, unidad padre, nivel en el árbol, contadores
 * e indicadores de la cadena.
 */
class UnidadDTO
{
    /**
     * @param  array{id: int, nombre: string, nivel: int}  $tipo
     * @param  array{id: int, codigo: string, nombre: string, estado: string|null}|null  $unidadPadre
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly array $tipo,
        public readonly ?array $unidadPadre,
        public readonly int $nivelArbol,
        public readonly string $estado,
        public readonly bool $tieneDependientes,
        public readonly int $dependientesActivas,
        public readonly int $posicionesVigentes,
        public readonly bool $admiteHijas,
        public readonly bool $sigueCadena,
    ) {}

    public static function desde(Unidad $unidad): static
    {
        return new static(...self::campos($unidad));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function campos(Unidad $unidad): array
    {
        return [
            'id' => $unidad->id,
            'codigo' => $unidad->codigo,
            'nombre' => $unidad->nombre,
            'tipo' => ['id' => $unidad->tipo->id, 'nombre' => $unidad->tipo->nombre, 'nivel' => $unidad->tipo->nivel],
            'unidadPadre' => $unidad->padre === null ? null : [
                'id' => $unidad->padre->id,
                'codigo' => $unidad->padre->codigo,
                'nombre' => $unidad->padre->nombre,
                'estado' => $unidad->padre->estado?->value,
            ],
            'nivelArbol' => $unidad->nivel,
            'estado' => $unidad->estado->value,
            'tieneDependientes' => $unidad->tieneDependientes,
            'dependientesActivas' => $unidad->dependientesActivas,
            'posicionesVigentes' => $unidad->posicionesVigentes,
            'admiteHijas' => $unidad->admiteHijas,
            'sigueCadena' => $unidad->sigueCadena,
        ];
    }
}
