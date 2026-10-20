<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;

/** Lee del Plan de Formación vigente las acciones que consume Actividades (sin copiarlas). */
final class AccionesDesdePlan implements AccionesDePlanInterface
{
    public function __construct(private readonly PlanRepositoryInterface $planes) {}

    public function porIds(int $tenantId, array $accionIds): array
    {
        return $this->planes->accionesPorIds($tenantId, $accionIds);
    }

    public function obtener(int $tenantId, int $accionId): ?AccionDeActividad
    {
        return $this->planes->accionesPorIds($tenantId, [$accionId])[$accionId] ?? null;
    }

    public function elegibles(int $tenantId, array $idsConActividad, ?int $planId, ?string $q, int $pagina, int $porPagina): array
    {
        return $this->planes->accionesElegiblesParaActividad($tenantId, $idsConActividad, $planId, $q, $pagina, $porPagina);
    }

    public function deColaborador(int $tenantId, int $colaboradorId): array
    {
        $ids = $this->planes->idsDeAccionesDeColaboradorEnEjecucion($tenantId, $colaboradorId);

        return array_values($this->planes->accionesPorIds($tenantId, $ids));
    }

    public function planes(int $tenantId): array
    {
        return $this->planes->planesParaActividades($tenantId);
    }
}
