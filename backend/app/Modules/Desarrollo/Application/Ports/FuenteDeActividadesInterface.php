<?php

namespace App\Modules\Desarrollo\Application\Ports;

use DateTimeImmutable;

/** Actividades formativas, asistencias y calificaciones vistas desde Reportes (solo lectura, sin observaciones). */
interface FuenteDeActividadesInterface
{
    /**
     * @return list<array{actividad_id: int, codigo: string, accion_id: int, plan_id: int, nombre: string, tipo: string|null, estado: string, fecha_inicio: string|null, fecha_fin: string|null, horas: float|null, departamento_id: int|null, inscritos: int, calificados: int, promedio: float|null}>
     */
    public function actividades(int $tenantId, DateTimeImmutable $hoy): array;

    /**
     * Solo participantes con registro (los Pendientes no se materializan). Con `colaborador_id` o `actividad_id` se restringe.
     *
     * @param  array{colaborador_id?: int, actividad_id?: int}  $filtros
     * @return list<array{actividad_id: int, accion_id: int, plan_id: int, colaborador_id: int, asistencia: string, calificacion: int|null, horas: float|null, fecha_inicio: string|null, fecha_fin: string|null}>
     */
    public function asistencias(int $tenantId, DateTimeImmutable $hoy, array $filtros = []): array;
}
