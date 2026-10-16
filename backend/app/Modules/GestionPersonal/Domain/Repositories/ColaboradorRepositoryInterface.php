<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\FichaModificada;

interface ColaboradorRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?Colaborador;

    public function porIdentificacion(int $tenantId, string $tipo, string $clave): ?Colaborador;

    /** @return array{colaborador: Colaborador, hash: string, actor: int}|null */
    public function porOperacion(int $tenantId, string $operacionId): ?array;

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConflictoDeUnicidad
     */
    public function crear(int $tenantId, int $actorId, string $operacionId, string $hash, array $datos, string $clave, string $nombresBusqueda, string $apellidosBusqueda): Colaborador;

    /**
     * @param  array<string, mixed>  $cambios  Columnas a escribir (incluye claves y búsquedas recalculadas).
     *
     * @throws FichaModificada
     * @throws ConflictoDeUnicidad
     */
    public function actualizar(int $tenantId, int $id, int $version, int $actorId, array $cambios): Colaborador;

    /**
     * @param  list<string>  $tokens  Cada token debe aparecer en «nombres apellidos».
     * @param  list<int>|null  $idsPorPuesto  Fichas cuyo puesto coincide con la búsqueda (OR).
     * @param  list<int>|null  $incluirIds  Restringe a estos ids (filtros de estado y unidad).
     * @param  list<int>|null  $excluirIds  Excluye estos ids (estado Sin vínculo vigente).
     * @return array{items: list<Colaborador>, total: int}
     */
    public function listar(int $tenantId, array $tokens, ?string $codigo, ?array $idsPorPuesto, ?array $incluirIds, ?array $excluirIds, int $pagina, int $porPagina): array;

    public function hayFichas(int $tenantId): bool;
}
