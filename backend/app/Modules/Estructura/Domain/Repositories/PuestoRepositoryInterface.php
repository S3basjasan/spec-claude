<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\Puesto;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoModificado;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaConGrados;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\PaginaPuestos;

interface PuestoRepositoryInterface
{
    /**
     * Listado paginado por nombre (sin funciones, requisitos ni competencias). `$q` busca por código o por nombre
     * sin distinguir mayúsculas ni tildes; `$estado` nulo = todos.
     */
    public function listar(?string $q, ?EstadoPuesto $estado, int $pagina, int $porPagina): PaginaPuestos;

    /** Puesto con sus funciones, requisitos y competencias (activo o inactivo), o null. */
    public function obtener(int $id): ?Puesto;

    /**
     * Puestos existentes entre los ids dados, con sus listas, incluidos los inactivos.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Puesto>
     */
    public function obtenerPorIds(array $ids): array;

    /**
     * Puestos activos con sus listas, ordenados por nombre.
     *
     * @return array<int, Puesto>
     */
    public function obtenerActivos(): array;

    /**
     * Competencias activas con sus grados, ordenadas por nombre, para los selectores del formulario.
     *
     * @return array<int, CompetenciaConGrados>
     */
    public function competenciasDisponibles(): array;

    /**
     * Competencias existentes entre los ids dados (activas o inactivas), por id, para validar.
     *
     * @param  array<int, int>  $ids
     * @return array<int, CompetenciaConGrados>
     */
    public function competenciasPorIds(array $ids): array;

    /**
     * Crea el puesto Activo, con su código, sus listas y su historial, en una transacción.
     *
     * @throws NombreDePuestoDuplicado
     */
    public function crear(DatosPuesto $datos, ?int $actorId): Puesto;

    /**
     * Reemplaza el contenido del puesto si hay cambios reales (sin cambios no escribe nada).
     *
     * @throws PuestoNoEncontrado
     * @throws PuestoModificado
     * @throws NombreDePuestoDuplicado
     */
    public function actualizar(int $id, int $versionEsperada, DatosPuesto $datos, ?int $actorId): Puesto;

    /**
     * Activa o desactiva en una sola operación atómica. Si ya está en ese estado devuelve el puesto sin cambios.
     *
     * @throws PuestoNoEncontrado
     * @throws PuestoConPosicionesEnUso
     */
    public function cambiarEstado(int $id, EstadoPuesto $nuevo, ?int $actorId): Puesto;
}
