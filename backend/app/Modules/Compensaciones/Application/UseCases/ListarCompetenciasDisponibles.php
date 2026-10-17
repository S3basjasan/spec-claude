<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;

final class ListarCompetenciasDisponibles
{
    public function __construct(private readonly CompetenciasPort $competencias) {}

    /**
     * Competencias activas para crear o reasignar una categoría.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo: string}>
     */
    public function ejecutar(): array
    {
        return array_map(
            fn (array $c) => ['id' => $c['id'], 'codigo' => $c['codigo'], 'nombre' => $c['nombre'], 'tipo' => $c['tipo']],
            $this->competencias->activas(),
        );
    }
}
