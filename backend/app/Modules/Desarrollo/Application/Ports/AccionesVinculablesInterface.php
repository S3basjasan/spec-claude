<?php

namespace App\Modules\Desarrollo\Application\Ports;

use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;

/**
 * Lectura de las acciones del Plan de Formación que se pueden vincular a una acción del PDI. Solo lectura: el Plan de
 * Formación nunca se modifica desde el PDI (FR-023).
 */
interface AccionesVinculablesInterface
{
    /**
     * Acciones no canceladas de planes Aprobado o Ejecutando en las que el colaborador es participante (FR-019).
     *
     * @return list<AccionDeActividad>
     */
    public function deColaborador(int $tenantId, int $colaboradorId): array;

    /**
     * Acciones por identificador, incluidas las canceladas y las de planes en cualquier estado.
     *
     * @param  list<int>  $accionIds
     * @return array<int, AccionDeActividad> accion_id => acción
     */
    public function porIds(int $tenantId, array $accionIds): array;
}
