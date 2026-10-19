<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\FuenteDeActividadesInterface;
use App\Modules\Desarrollo\Application\UseCases\ConsultarActividadesParaOtrosModulos;
use DateTimeImmutable;

/** Lee actividades y asistencias con las mismas consultas de solo lectura que Actividades publica para otros consumidores. */
final class ActividadesParaReportes implements FuenteDeActividadesInterface
{
    public function __construct(private readonly ConsultarActividadesParaOtrosModulos $consultas) {}

    public function actividades(int $tenantId, DateTimeImmutable $hoy): array
    {
        return $this->consultas->actividades($tenantId, ['hoy' => $hoy->format('Y-m-d')]);
    }

    public function asistencias(int $tenantId, DateTimeImmutable $hoy, array $filtros = []): array
    {
        return $this->consultas->asistencias($tenantId, ['hoy' => $hoy->format('Y-m-d')] + $filtros);
    }
}
