<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDePlanesInterface;

/** Opciones de los filtros de los reportes: departamentos activos y planes Ejecutando o Cerrado. */
final class ObtenerOpcionesDeReportes
{
    public function __construct(
        private readonly DepartamentosInterface $departamentos,
        private readonly FuenteDePlanesInterface $planes,
    ) {}

    /**
     * @return array{departamentos: list<array{id: int, nombre: string}>, planes: list<array{id: int, codigo: string, nombre: string, anio: int, estado: string}>}
     */
    public function ejecutar(int $tenantId): array
    {
        return ['departamentos' => $this->departamentos->activos(), 'planes' => $this->planes->planes($tenantId)];
    }
}
