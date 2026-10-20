<?php

namespace App\Modules\Desarrollo\Application\Ports;

use DateTimeImmutable;

/**
 * Planes y acciones del Plan de Formación vistos desde Reportes (solo lectura). El estado de cada acción se calcula
 * con la fecha dada; Reportes no copia ni modifica estos datos.
 */
interface FuenteDePlanesInterface
{
    /**
     * Acciones de todos los planes del tenant (también canceladas y de planes en cualquier estado).
     *
     * @return list<array{accion_id: int, plan_id: int, plan_codigo: string, plan_nombre: string, plan_estado: string, nombre: string, tipo: string|null, estado_accion: string, fecha_inicio: string|null, fecha_fin: string|null, horas: float|null, costo: float|null, cancelada: bool, competencia_ids: list<int>, participante_ids: list<int>}>
     */
    public function acciones(int $tenantId, DateTimeImmutable $hoy): array;

    /**
     * Planes Ejecutando o Cerrado, para el filtro por plan.
     *
     * @return list<array{id: int, codigo: string, nombre: string, anio: int, estado: string}>
     */
    public function planes(int $tenantId): array;
}
