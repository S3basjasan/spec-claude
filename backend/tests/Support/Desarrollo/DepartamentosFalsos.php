<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;

/** Departamentos sintéticos: `[id => nombre]` activos y una lista de ids inactivos que existen pero no se ofrecen. */
final class DepartamentosFalsos implements DepartamentosInterface
{
    /**
     * @param  array<int, string>  $activos
     * @param  array<int, string>  $inactivos
     */
    public function __construct(public array $activos = [], public array $inactivos = []) {}

    public function activos(): array
    {
        $r = [];
        foreach ($this->activos as $id => $nombre) {
            $r[] = ['id' => $id, 'nombre' => $nombre];
        }

        return $r;
    }

    public function porIds(array $ids): array
    {
        $todos = $this->activos + $this->inactivos;

        return array_intersect_key($todos, array_flip($ids));
    }

    public function estaActivo(int $id): bool
    {
        return isset($this->activos[$id]);
    }
}
