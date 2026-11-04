<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;

/**
 * Unidades a las que aplica una categoría: al menos una, enteros positivos sin repetir, ordenados.
 * La asignación es directa: no hay herencia por la jerarquía de unidades.
 */
final class UnidadesAsignadas
{
    /** @var array<int, int> */
    private readonly array $ids;

    /**
     * @param  array<int, mixed>  $ids
     *
     * @throws DatosDeCategoriaInvalidos
     */
    public function __construct(array $ids)
    {
        $limpios = [];

        foreach ($ids as $id) {
            if (is_string($id) && ctype_digit($id)) {
                $id = (int) $id;
            }

            if (! is_int($id) || $id < 1) {
                throw new DatosDeCategoriaInvalidos('unidades_ids', 'Elige al menos una unidad.');
            }

            $limpios[$id] = $id;
        }

        if ($limpios === []) {
            throw new DatosDeCategoriaInvalidos('unidades_ids', 'Elige al menos una unidad.');
        }

        sort($limpios);

        $this->ids = array_values($limpios);
    }

    /** @return array<int, int> */
    public function ids(): array
    {
        return $this->ids;
    }

    public function contiene(int $unidadId): bool
    {
        return in_array($unidadId, $this->ids, true);
    }

    /** JSON de ids ordenados, p. ej. `[1,2,5]`: es el valor que se audita. */
    public function comoJson(): string
    {
        return (string) json_encode($this->ids);
    }
}
