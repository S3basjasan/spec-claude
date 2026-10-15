<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;

/**
 * Árbol de unidades armado en memoria a partir de `id` y `unidad_padre_id`. Puro: sin Laravel ni Eloquent.
 *
 * Ordena en profundidad (cada unidad seguida de sus dependientes), calcula el nivel de cada una y encuentra
 * ancestras y descendientes. Los hermanos y las raíces se ordenan por la parte numérica del código
 * (`UO-9` antes que `UO-10` y `UO-1000`). Es defensivo ante datos corruptos: un nodo visitado no se repite.
 */
final class ArbolDeUnidades
{
    /** @var array<int, NodoDeUnidad> */
    private array $nodos = [];

    /** @var array<int, array<int, int>> padre => hijas ordenadas */
    private array $hijas = [];

    /** @var array<int, int> nivel de cada nodo alcanzado desde una raíz */
    private array $niveles = [];

    /** @var array<int, int> */
    private array $orden = [];

    /**
     * @param  array<int, NodoDeUnidad>  $nodos
     */
    public function __construct(array $nodos)
    {
        foreach ($nodos as $nodo) {
            $this->nodos[$nodo->id] = $nodo;
        }

        foreach ($this->nodos as $nodo) {
            if ($nodo->unidadPadreId !== null && isset($this->nodos[$nodo->unidadPadreId])) {
                $this->hijas[$nodo->unidadPadreId][] = $nodo->id;
            }
        }
        foreach ($this->hijas as $padre => $ids) {
            $this->hijas[$padre] = $this->ordenarPorCodigo($ids);
        }

        $raices = array_keys(array_filter(
            $this->nodos,
            fn (NodoDeUnidad $nodo) => $nodo->unidadPadreId === null || ! isset($this->nodos[$nodo->unidadPadreId]),
        ));
        foreach ($this->ordenarPorCodigo($raices) as $raiz) {
            $this->recorrer($raiz, 0);
        }

        // Datos corruptos (por ejemplo un ciclo): lo que no se alcanzó desde una raíz se añade sin repetir.
        foreach ($this->ordenarPorCodigo(array_keys($this->nodos)) as $id) {
            $this->recorrer($id, 0);
        }
    }

    /**
     * Ids en orden jerárquico.
     *
     * @return array<int, int>
     */
    public function ordenados(): array
    {
        return $this->orden;
    }

    public function nodo(int $id): ?NodoDeUnidad
    {
        return $this->nodos[$id] ?? null;
    }

    public function nivelDe(int $id): int
    {
        return $this->niveles[$id] ?? 0;
    }

    /**
     * Hijas directas de una unidad, por código.
     *
     * @return array<int, int>
     */
    public function hijasDe(int $id): array
    {
        return $this->hijas[$id] ?? [];
    }

    /**
     * Dependientes directas e indirectas de una unidad.
     *
     * @return array<int, int>
     */
    public function descendientesDe(int $id): array
    {
        $resultado = [];
        $visitadas = [$id => true];
        $pendientes = $this->hijasDe($id);

        while ($pendientes !== []) {
            $actual = array_shift($pendientes);
            if (isset($visitadas[$actual])) {
                continue;
            }
            $visitadas[$actual] = true;
            $resultado[] = $actual;
            array_push($pendientes, ...$this->hijasDe($actual));
        }

        return $resultado;
    }

    /**
     * La unidad y sus dependientes activas, directas e indirectas (lo que alcanza una desactivación en cascada).
     *
     * @return array<int, int>
     */
    public function rama(int $id): array
    {
        $resultado = [$id];
        foreach ($this->descendientesDe($id) as $descendiente) {
            if ($this->nodos[$descendiente]->estado === EstadoUnidad::Activa) {
                $resultado[] = $descendiente;
            }
        }

        return $resultado;
    }

    /**
     * Unidades de las que depende, de la más cercana (su padre) a la raíz.
     *
     * @return array<int, int>
     */
    public function ancestrasDe(int $id): array
    {
        $resultado = [];
        $visitadas = [$id => true];
        $actual = $this->nodos[$id]->unidadPadreId ?? null;

        while ($actual !== null && isset($this->nodos[$actual]) && ! isset($visitadas[$actual])) {
            $visitadas[$actual] = true;
            $resultado[] = $actual;
            $actual = $this->nodos[$actual]->unidadPadreId;
        }

        return $resultado;
    }

    private function recorrer(int $id, int $nivel): void
    {
        if (isset($this->niveles[$id])) {
            return;
        }
        $this->niveles[$id] = $nivel;
        $this->orden[] = $id;

        foreach ($this->hijas[$id] ?? [] as $hija) {
            $this->recorrer($hija, $nivel + 1);
        }
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function ordenarPorCodigo(array $ids): array
    {
        usort($ids, function (int $a, int $b): int {
            return [$this->numeroDeCodigo($this->nodos[$a]), $a] <=> [$this->numeroDeCodigo($this->nodos[$b]), $b];
        });

        return array_values($ids);
    }

    private function numeroDeCodigo(NodoDeUnidad $nodo): int
    {
        return (int) preg_replace('/\D+/', '', $nodo->codigo);
    }
}
