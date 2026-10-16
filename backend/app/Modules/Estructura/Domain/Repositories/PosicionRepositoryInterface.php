<?php

namespace App\Modules\Estructura\Domain\Repositories;

/**
 * Lectura de posiciones para otros módulos (GP-02). Las posiciones se gestionan en otra funcionalidad;
 * aquí solo se consultan, con su puesto y unidad vigentes.
 */
interface PosicionRepositoryInterface
{
    /**
     * @return array{id: int, tenant_id: int, estado: string, puesto_id: int, puesto: string, puesto_activo: bool, unidad_id: int|null, unidad: string|null, unidad_activa: bool, jefatura: null}|null
     */
    public function obtener(int $id): ?array;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>> Indexado por id de posición.
     */
    public function porIds(array $ids): array;

    /**
     * Posiciones del tenant, no suprimidas, con puesto activo y unidad activa (sin considerar ocupación).
     *
     * @return array{items: array<int, array{id: int, puesto: string, unidad: string, jefatura: null}>, total: int}
     */
    public function asignables(int $tenantId, ?string $texto, int $pagina, int $porPagina): array;

    /**
     * Motivo por el que una posición no admite nuevas asignaciones, o null si las admite.
     *
     * @return string|null `inexistente`, `otro_tenant`, `suprimida`, `puesto_inactivo` o `unidad_inactiva`
     */
    public function motivoNoAsignable(int $id, int $tenantId): ?string;

    /** @return array<int, int> */
    public function idsPorPuesto(string $texto): array;

    /** @return array<int, int> */
    public function idsPorUnidad(int $unidadId): array;

    /** Cantidad de posiciones Ocupadas o Vacantes del puesto (las que lo mantienen en uso). */
    public function contarEnUsoDelPuesto(int $puestoId): int;

    /**
     * Posiciones Suprimidas de un puesto (las únicas que no bloquean por su estado guardado).
     *
     * @return array<int, int>
     */
    public function suprimidasDelPuesto(int $puestoId): array;

    /**
     * Posiciones Suprimidas de las unidades dadas, por unidad.
     *
     * @param  array<int, int>  $unidadIds
     * @return array<int, array<int, int>> unidad_id => posicion_ids
     */
    public function suprimidasDeUnidades(array $unidadIds): array;
}
