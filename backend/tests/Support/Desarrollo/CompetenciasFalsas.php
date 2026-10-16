<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;

/** Diccionario de competencias falso: ids activos e inactivos. */
final class CompetenciasFalsas implements CompetenciasInterface
{
    /**
     * @param  list<int>  $activas
     * @param  list<int>  $inactivas
     */
    public function __construct(private readonly array $activas = [1, 2, 3], private readonly array $inactivas = [9]) {}

    public function todas(): array
    {
        $filas = [];
        foreach (array_merge($this->activas, $this->inactivas) as $id) {
            $filas[$id] = $this->fila($id);
        }
        ksort($filas);

        return array_values($filas);
    }

    public function obtener(int $id): ?array
    {
        return $this->existe($id) ? $this->fila($id) + ['grados' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd']] : null;
    }

    public function porIds(array $ids): array
    {
        $r = [];
        foreach ($ids as $id) {
            if ($this->existe($id)) {
                $r[$id] = $this->fila($id);
            }
        }

        return $r;
    }

    public function existe(int $id): bool
    {
        return in_array($id, $this->activas, true) || in_array($id, $this->inactivas, true);
    }

    public function estaActiva(int $id): bool
    {
        return in_array($id, $this->activas, true);
    }

    /** @return array{id: int, codigo: string, nombre: string, descripcion: string, tipo: string, estado: string} */
    private function fila(int $id): array
    {
        return [
            'id' => $id,
            'codigo' => sprintf('COM-%03d', $id),
            'nombre' => "Competencia {$id}",
            'descripcion' => '',
            'tipo' => 'Cardinal',
            'estado' => $this->estaActiva($id) ? 'Activo' : 'Inactivo',
        ];
    }
}
