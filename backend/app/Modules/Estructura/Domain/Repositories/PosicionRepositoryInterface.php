<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\Posicion;
use App\Modules\Estructura\Domain\Exceptions\PosicionInvalida;
use App\Modules\Estructura\Domain\Exceptions\PosicionModificada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoSuprimible;
use App\Modules\Estructura\Domain\ValueObjects\DatosPosicion;
use App\Modules\Estructura\Domain\ValueObjects\FiltroDePosiciones;
use App\Modules\Estructura\Domain\ValueObjects\NodoDePosicion;
use App\Modules\Estructura\Domain\ValueObjects\OpcionesDePosicion;
use App\Modules\Estructura\Domain\ValueObjects\PaginaDePosiciones;

/**
 * Puerto de persistencia de las posiciones. El puesto, la unidad y el supervisor se leen siempre en vivo.
 */
interface PosicionRepositoryInterface
{
    /** Página de posiciones ordenadas por código (numéricamente), con `subordinadasVigentes` de cada una. */
    public function listar(FiltroDePosiciones $filtro): PaginaDePosiciones;

    /**
     * Posiciones vigentes (Ocupadas y Vacantes) del tenant, por código, con puesto, unidad, supervisor y ocupante. Las
     * Suprimidas nunca se incluyen. Para el organigrama.
     *
     * @return array<int, Posicion>
     */
    public function vigentes(int $tenantId): array;

    /** Detalle de una posición (con subordinadas y datos de auditoría), en cualquier estado, o null si no existe. */
    public function obtener(int $id): ?Posicion;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Posicion>
     */
    public function obtenerPorIds(array $ids): array;

    /**
     * Selectores del formulario. Con `excluir` (la posición que se edita) las candidatas a supervisor no incluyen esa
     * posición ni ninguna de sus subordinadas.
     */
    public function opciones(?int $excluir): OpcionesDePosicion;

    /**
     * Posiciones no Suprimidas con su supervisor, para el organigrama y las reglas de la estructura.
     *
     * @return array<int, NodoDePosicion>
     */
    public function arbolDeSupervision(): array;

    /**
     * Crea la posición `Vacante`, sin ocupante, con código `POS-####` y versión 1.
     *
     * @throws PosicionInvalida
     */
    public function crear(DatosPosicion $datos, ?int $actorId): Posicion;

    /**
     * Cambia unidad, supervisor y modalidad. Sin cambios reales no escribe nada (ni versión ni historial).
     *
     * @throws PosicionNoEncontrada
     * @throws PosicionModificada
     * @throws PosicionInvalida
     */
    public function actualizar(int $id, int $versionEsperada, DatosPosicion $datos, ?int $actorId): Posicion;

    /**
     * Pasa la posición Vacante sin subordinadas vigentes a `Suprimida`, en una sola operación atómica. Si ya está
     * Suprimida no cambia nada. No toca la versión ni la última modificación.
     *
     * @throws PosicionNoEncontrada
     * @throws PosicionNoSuprimible
     */
    public function suprimir(int $id, ?int $actorId): Posicion;

    // ------------------------------------------------- Lectura para otros módulos (GP-02)

    /**
     * Datos planos de una posición (también Suprimida) para otros módulos, con su puesto y unidad vigentes.
     *
     * @return array{id: int, codigo: string|null, tenant_id: int, estado: string, modalidad: string|null, puesto_id: int, puesto: string, puesto_activo: bool, unidad_id: int|null, unidad: string|null, unidad_activa: bool, jefatura: string|null, supervisor_id: int|null, ocupante_id: int|null}|null
     */
    public function datosDe(int $id): ?array;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>> Indexado por id de posición.
     */
    public function datosPorIds(array $ids): array;

    /**
     * Posiciones del tenant, no suprimidas, con puesto activo y unidad activa (sin considerar ocupación).
     *
     * @return array{items: array<int, array{id: int, puesto: string, unidad: string, jefatura: string|null}>, total: int}
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
