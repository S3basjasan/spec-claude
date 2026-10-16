<?php

namespace App\Modules\Estructura\Domain\Services;

/**
 * Jerarquía de unidades para las reglas de las posiciones. Puro: sin Laravel ni Eloquent.
 *
 * La raíz es la unidad sin unidad padre. Una posición supervisora es válida si su unidad es la misma que la de la
 * posición o una de sus unidades superiores (su padre, la padre de esta y así hasta la raíz).
 */
final class JerarquiaDeUnidades
{
    public function __construct(private readonly ArbolDeUnidades $arbol) {}

    /** Id de la unidad raíz (la primera sin unidad padre), o nulo si no hay unidades. */
    public function raiz(): ?int
    {
        foreach ($this->arbol->ordenados() as $id) {
            if ($this->arbol->nodo($id)?->unidadPadreId === null) {
                return $id;
            }
        }

        return null;
    }

    public function esRaiz(int $unidadId): bool
    {
        return $unidadId === $this->raiz();
    }

    /**
     * Unidades superiores de una unidad, de la más cercana (su padre) a la raíz.
     *
     * @return array<int, int>
     */
    public function superioresDe(int $unidadId): array
    {
        return $this->arbol->ancestrasDe($unidadId);
    }

    /** Indica si `$candidata` es la misma unidad que `$unidad` o una de sus unidades superiores. */
    public function esIgualOSuperior(int $candidata, int $unidad): bool
    {
        return $candidata === $unidad || in_array($candidata, $this->superioresDe($unidad), true);
    }
}
