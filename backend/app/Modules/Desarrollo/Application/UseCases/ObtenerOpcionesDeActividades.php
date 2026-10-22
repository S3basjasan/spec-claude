<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;

/** Opciones de los filtros de actividades: departamentos activos, competencias y planes con actividades posibles. */
final class ObtenerOpcionesDeActividades
{
    public function __construct(
        private readonly DepartamentosInterface $departamentos,
        private readonly CompetenciasInterface $competencias,
        private readonly AccionesDePlanInterface $acciones,
    ) {}

    /**
     * @return array{departamentos: list<array{id: int, nombre: string}>, competencias: list<array{id: int, nombre: string}>, planes: list<array{id: int, codigo: string, nombre: string, anio: int, estado: string}>}
     */
    public function ejecutar(int $tenantId): array
    {
        return [
            'departamentos' => $this->departamentos->activos(),
            'competencias' => array_values(array_map(fn (array $c) => ['id' => (int) $c['id'], 'nombre' => (string) $c['nombre']], $this->competencias->todas())),
            'planes' => $this->acciones->planes($tenantId),
        ];
    }
}
