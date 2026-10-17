<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;

final class ListarCompetenciasParaFiltro
{
    public function __construct(private readonly CompetenciasPort $competencias) {}

    /**
     * Opciones del filtro de competencia del listado: el administrador dispone de todas (las inactivas con su
     * estado); los demás, solo de las activas.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo: string, estado: string}>
     */
    public function ejecutar(bool $puedeGestionar): array
    {
        return $puedeGestionar ? $this->competencias->todas() : $this->competencias->activas();
    }
}
