<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\FuenteDeMicrolearningInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;

/** Lee las completaciones de micro-contenidos por el repositorio de progresos de Micro-Learning. */
final class MicrolearningParaReportes implements FuenteDeMicrolearningInterface
{
    public function __construct(private readonly ProgresoMicroRepositoryInterface $progresos) {}

    public function completaciones(int $tenantId, array $filtros = []): array
    {
        return array_values($this->progresos->completaciones($tenantId, $filtros));
    }
}
