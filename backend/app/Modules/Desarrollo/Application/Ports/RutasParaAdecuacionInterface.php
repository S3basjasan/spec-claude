<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Inscripciones y niveles de las rutas de carrera (feature 019), que la adecuación usa para definir el puesto objetivo. */
interface RutasParaAdecuacionInterface
{
    /**
     * @return list<array{inscripcion_id: int, ruta_id: int, ruta_nombre: string, nivel_actual: array{id: int, orden: int, puesto_id: int, nombre: string}|null, nivel_objetivo: array{id: int, orden: int, puesto_id: int, nombre: string, experiencia_minima_anios?: float|null}|null}>
     */
    public function inscripcionesActivas(int $tenantId, int $colaboradorId): array;

    /**
     * @return array{nivel_id: int, ruta_id: int, ruta_nombre: string, orden: int, puesto_id: int, nombre: string, experiencia_minima_anios: float|null}|null
     */
    public function nivel(int $tenantId, int $nivelId): ?array;

    /**
     * Rutas del tenant (de cualquier estado), para los filtros del mapa de talento.
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function rutas(int $tenantId): array;
}
