<?php

namespace App\Modules\Compensaciones\Application\Ports;

/**
 * Lectura del Diccionario de Competencias (módulo Estructura) a través de su contrato público.
 * Cada competencia es `{id, codigo, nombre, tipo, estado}` con estado `Activo` o `Inactivo`.
 */
interface CompetenciasPort
{
    /**
     * Competencias activas ordenadas por nombre.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo: string, estado: string}>
     */
    public function activas(): array;

    /**
     * Todas las competencias, activas e inactivas, ordenadas por nombre.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo: string, estado: string}>
     */
    public function todas(): array;

    /**
     * @return array{id: int, codigo: string, nombre: string, tipo: string, estado: string}|null
     */
    public function porId(int $id): ?array;

    /**
     * Competencias existentes entre los ids, indexadas por id.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, codigo: string, nombre: string, tipo: string, estado: string}>
     */
    public function porIds(array $ids): array;

    /**
     * Ids de las competencias cuyo nombre contiene el texto, sin distinguir mayúsculas ni tildes.
     *
     * @return array<int, int>
     */
    public function idsPorNombre(string $texto): array;

    public function estaActiva(int $id): bool;
}
