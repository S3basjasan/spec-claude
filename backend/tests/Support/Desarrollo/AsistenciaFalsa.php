<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;

/** Asistencia falsa: `[colaborador_id => [accion_id, ...]]` con asistencia Presente. */
class AsistenciaFalsa implements AsistenciaInterface
{
    /** @param array<int, list<int>> $presentes */
    public function __construct(public array $presentes = []) {}

    public function presentesPorAccion(array $accionIds, int $colaboradorId): array
    {
        return array_values(array_intersect($accionIds, $this->presentes[$colaboradorId] ?? []));
    }
}
