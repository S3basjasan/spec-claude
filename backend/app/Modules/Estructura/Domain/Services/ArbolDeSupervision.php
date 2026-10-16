<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\ValueObjects\EstadoPosicion;
use App\Modules\Estructura\Domain\ValueObjects\NodoDePosicion;

/**
 * Árbol de supervisión armado en memoria a partir de `id` y `posicion_jefe_id`. Puro: sin Laravel ni Eloquent.
 *
 * Solo cuenta las posiciones no Suprimidas. Es defensivo ante datos corruptos (un ciclo): un nodo visitado no se repite.
 */
final class ArbolDeSupervision
{
    /** @var array<int, NodoDePosicion> */
    private array $nodos = [];

    /** @var array<int, array<int, int>> supervisor => subordinadas directas */
    private array $directas = [];

    /**
     * @param  array<int, NodoDePosicion>  $nodos
     */
    public function __construct(array $nodos)
    {
        foreach ($nodos as $nodo) {
            if ($nodo->estado === EstadoPosicion::Suprimida) {
                continue;
            }
            $this->nodos[$nodo->id] = $nodo;
        }

        foreach ($this->nodos as $nodo) {
            if ($nodo->supervisorId !== null && isset($this->nodos[$nodo->supervisorId])) {
                $this->directas[$nodo->supervisorId][] = $nodo->id;
            }
        }
    }

    /**
     * Subordinadas directas de una posición.
     *
     * @return array<int, int>
     */
    public function subordinadasDirectas(int $id): array
    {
        return $this->directas[$id] ?? [];
    }

    /**
     * Subordinadas directas e indirectas de una posición.
     *
     * @return array<int, int>
     */
    public function subordinadasDe(int $id): array
    {
        $resultado = [];
        $visitadas = [$id => true];
        $pendientes = $this->subordinadasDirectas($id);

        while ($pendientes !== []) {
            $actual = array_shift($pendientes);
            if (isset($visitadas[$actual])) {
                continue;
            }
            $visitadas[$actual] = true;
            $resultado[] = $actual;
            array_push($pendientes, ...$this->subordinadasDirectas($actual));
        }

        return $resultado;
    }

    /** Indica si `$candidata` es subordinada, directa o indirecta, de `$de`. */
    public function esSubordinada(int $candidata, int $de): bool
    {
        return in_array($candidata, $this->subordinadasDe($de), true);
    }
}
