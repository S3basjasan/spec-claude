<?php

namespace App\Modules\Desarrollo\Application\Ports;

use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;

/**
 * Lectura de las acciones del Plan de Formación desde Actividades. Siempre devuelve los datos vigentes del plan:
 * Actividades no los copia ni los modifica.
 */
interface AccionesDePlanInterface
{
    /**
     * @param  list<int>  $accionIds
     * @return array<int, AccionDeActividad> accion_id => acción (incluye canceladas y planes de cualquier estado)
     */
    public function porIds(int $tenantId, array $accionIds): array;

    public function obtener(int $tenantId, int $accionId): ?AccionDeActividad;

    /**
     * Acciones no canceladas de planes Ejecutando que no están en `$idsConActividad`.
     *
     * @param  list<int>  $idsConActividad
     * @return array{items: list<AccionDeActividad>, total: int}
     */
    public function elegibles(int $tenantId, array $idsConActividad, ?int $planId, ?string $q, int $pagina, int $porPagina): array;

    /**
     * Acciones en las que el colaborador está inscrito, de planes Ejecutando o Cerrado.
     *
     * @return list<AccionDeActividad>
     */
    public function deColaborador(int $tenantId, int $colaboradorId): array;

    /**
     * Planes que pueden tener actividades (Ejecutando o Cerrado), para el filtro por plan.
     *
     * @return list<array{id: int, codigo: string, nombre: string, anio: int, estado: string}>
     */
    public function planes(int $tenantId): array;
}
