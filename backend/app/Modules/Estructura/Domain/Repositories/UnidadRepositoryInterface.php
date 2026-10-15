<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Exceptions\DesactivacionBloqueada;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadModificada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\ImpactoDeDesactivacion;
use App\Modules\Estructura\Domain\ValueObjects\OpcionesDeUnidad;

/**
 * Puerto de persistencia de las unidades organizacionales. El tipo y la unidad padre se leen siempre en vivo.
 */
interface UnidadRepositoryInterface
{
    /**
     * Todas las unidades (activas e inactivas) en orden jerárquico, con su nivel en el árbol, los contadores
     * `dependientesActivas` (rama activa) y `posicionesVigentes`, y los indicadores de la cadena, calculados sobre el
     * árbol completo.
     *
     * @return array<int, Unidad>
     */
    public function todas(): array;

    /** Detalle de una unidad (con dependientes y datos de auditoría), activa o inactiva, o null si no existe. */
    public function obtener(int $id): ?Unidad;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Unidad>
     */
    public function obtenerPorIds(array $ids): array;

    /**
     * Selectores de los formularios. Sin `reubicar`: la cadena y todas las unidades en orden de árbol. Con `reubicar`
     * (la unidad que se edita): solo las unidades activas del tipo inmediatamente superior, sin ella ni sus dependientes.
     */
    public function opciones(?int $reubicar): OpcionesDeUnidad;

    /** Dependientes activas que se desactivarían y unidades de la rama con posiciones vigentes. */
    public function impactoDeDesactivacion(int $id): ImpactoDeDesactivacion;

    /**
     * El tipo lo calcula el repositorio: el del nivel inmediatamente inferior al de la unidad padre.
     *
     * @throws UnidadInvalida
     */
    public function crear(DatosUnidad $datos, ?int $actorId): Unidad;

    /**
     * Sin cambios reales no escribe nada (ni versión ni auditoría).
     *
     * @throws UnidadNoEncontrada
     * @throws UnidadModificada
     * @throws UnidadInvalida
     */
    public function actualizar(int $id, int $versionEsperada, DatosUnidad $datos, ?int $actorId): Unidad;

    /**
     * Desactivar es en cascada y atómico (todas las unidades o ninguna); reactivar es unidad por unidad. No toca versión
     * ni última modificación.
     *
     * @throws UnidadNoEncontrada
     * @throws UnidadInvalida
     * @throws DesactivacionBloqueada
     * @throws UnidadNoReactivable
     */
    public function cambiarEstado(int $id, EstadoUnidad $estado, ?int $actorId): Unidad;
}
