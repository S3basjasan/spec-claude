<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Micro-contenidos completados vistos desde Reportes (solo lectura). */
interface FuenteDeMicrolearningInterface
{
    /**
     * Completaciones del tenant; `desde` y `hasta` (AAAA-MM-DD) filtran por fecha de finalización.
     *
     * @param  array{colaborador_id?: int, desde?: string, hasta?: string}  $filtros
     * @return list<array{microcontenido_id: int, codigo: string, titulo: string, tipo: string, duracion_minutos: int, colaborador_id: int, fecha_inicio: string, fecha_completado: string}>
     */
    public function completaciones(int $tenantId, array $filtros = []): array;
}
