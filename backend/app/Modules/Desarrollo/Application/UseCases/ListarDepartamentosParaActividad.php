<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;

/** Departamentos activos para el formulario y el filtro de actividades. */
final class ListarDepartamentosParaActividad
{
    public function __construct(private readonly DepartamentosInterface $departamentos) {}

    /** @return list<array{id: int, nombre: string}> */
    public function ejecutar(): array
    {
        return $this->departamentos->activos();
    }
}
