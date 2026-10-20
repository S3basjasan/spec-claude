<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Estado y niveles de cada competencia en cada DNF vistos desde Reportes (solo lectura). */
interface FuenteDeDnfInterface
{
    /**
     * Ordenadas por fecha y por id de DNF.
     *
     * @param  array{fecha_desde?: string, fecha_hasta?: string, colaborador_id?: int, colaborador_ids?: list<int>}  $filtros
     * @return list<array{dnf_id: int, fecha: string, colaborador_id: int, competencia_id: int, estado: string, nivel_requerido: string, nivel_actual: string|null}>
     */
    public function evaluaciones(int $tenantId, array $filtros = []): array;
}
