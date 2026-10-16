<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\NombreDeCategoriaDuplicado;

/**
 * Persistencia del catálogo de categorías documentales. Sin ningún método de eliminación.
 */
interface CategoriaRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?CategoriaDocumental;

    /**
     * @param  'Activa'|'Inactiva'|'Todas'  $estado
     * @return array{items: list<CategoriaDocumental>, total: int} Orden por nombre de búsqueda e id.
     */
    public function listar(int $tenantId, ?string $q, string $estado, int $pagina, int $porPagina): array;

    /** Si ya existe, en el tenant, otra categoría (distinta de `$excluirId`) con esa clave de nombre. */
    public function existePorClave(int $tenantId, string $nombreClave, ?int $excluirId = null): bool;

    /**
     * @throws NombreDeCategoriaDuplicado
     */
    public function crear(int $tenantId, int $actorId, string $nombre, string $nombreClave, string $nombreBusqueda): CategoriaDocumental;

    /**
     * @param  array<string, string>  $cambios  Columnas a escribir (nombre, nombre_clave, nombre_busqueda y/o estado).
     *
     * @throws CategoriaNoEncontrada
     * @throws CategoriaModificada
     * @throws NombreDeCategoriaDuplicado
     */
    public function actualizar(int $tenantId, int $id, int $version, int $actorId, array $cambios): CategoriaDocumental;

    /**
     * Toma el bloqueo de la fila hasta el commit del llamador sin modificarla.
     *
     * @throws CategoriaInactiva Inexistente, de otro tenant o inactiva.
     */
    public function confirmarActiva(int $tenantId, int $id): void;

    /** @return list<CategoriaDocumental> Activas, ordenadas por nombre. */
    public function activas(int $tenantId): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, CategoriaDocumental> Por id; los ajenos o inexistentes se omiten.
     */
    public function porIds(int $tenantId, array $ids): array;
}
