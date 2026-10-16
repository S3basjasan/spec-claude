<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;

/** Nombres sintéticos y deterministas: «Colaborador 12», «Puesto 3», «Competencia 4», «Unidad 2». */
final class NombresDeReferenciaFalsos implements NombresDeReferenciaInterface
{
    /** @param  list<int>  $competenciasInactivas */
    public function __construct(private readonly array $competenciasInactivas = []) {}

    public function colaboradores(int $tenantId, array $ids): array
    {
        return array_combine($ids, array_map(fn (int $id) => "Colaborador {$id}", $ids)) ?: [];
    }

    public function puestos(array $ids): array
    {
        $r = [];
        foreach ($ids as $id) {
            $r[$id] = ['codigo' => sprintf('PU-%03d', $id), 'nombre' => "Puesto {$id}"];
        }

        return $r;
    }

    public function competencias(array $ids): array
    {
        $r = [];
        foreach ($ids as $id) {
            $r[$id] = [
                'codigo' => sprintf('COM-%03d', $id),
                'nombre' => "Competencia {$id}",
                'activa' => ! in_array($id, $this->competenciasInactivas, true),
            ];
        }

        return $r;
    }

    public function unidades(array $ids): array
    {
        return array_combine($ids, array_map(fn (int $id) => "Unidad {$id}", $ids)) ?: [];
    }
}
