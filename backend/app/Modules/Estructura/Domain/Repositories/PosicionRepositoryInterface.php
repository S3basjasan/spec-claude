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
}
