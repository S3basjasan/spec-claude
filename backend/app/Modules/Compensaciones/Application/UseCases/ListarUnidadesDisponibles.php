<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\Ports\UnidadesPort;

final class ListarUnidadesDisponibles
{
    public function __construct(private readonly UnidadesPort $unidades) {}

    /**
     * Unidades activas para el selector de unidades de una categoría, en orden jerárquico.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, estado: string}>
     */
    public function ejecutar(): array
    {
        return $this->unidades->activas();
    }
}
