<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;

/**
 * Adaptador PROVISIONAL: Gestión de Personal (GP-02: puesto, vínculo y departamento del colaborador) aún no existe,
 * por lo que no hay colaboradores evaluables. Al implementarse se sustituye por uno que llame a su Facade
 * (la lista de subunidades se obtiene con EstructuraFacade::getArbolDeUnidades()) y se cambia el binding.
 */
final class ColaboradoresEvaluablesSinDatos implements ColaboradoresEvaluablesInterface
{
    public function evaluablesPorIds(int $tenantId, array $ids): array
    {
        return ['evaluables' => [], 'excluidos' => []];
    }

    public function evaluablesDeUnidad(int $tenantId, int $unidadId): array
    {
        return ['evaluables' => [], 'excluidos' => []];
    }

    public function evaluables(int $tenantId): array
    {
        return [];
    }

    public function departamentosConEvaluables(int $tenantId): array
    {
        return [];
    }
}
