<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\FuenteDeDnfInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;

/** Lee el estado y los niveles de cada competencia por DNF desde el repositorio de la DNF. */
final class DnfParaReportes implements FuenteDeDnfInterface
{
    public function __construct(private readonly DnfRepositoryInterface $dnf) {}

    public function evaluaciones(int $tenantId, array $filtros = []): array
    {
        return $this->dnf->evaluacionesDeCompetencias($tenantId, $filtros);
    }
}
