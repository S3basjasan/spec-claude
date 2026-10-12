<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaCompetencias;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

interface CompetenciaRepositoryInterface
{
    /** Listado ordenado por nombre (A–Z), sin grados. `$estado` nulo = activas e inactivas. */
    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaCompetencias;

    /** Competencia con sus cuatro grados (activa o inactiva), o null si no existe. */
    public function obtener(int $id): ?Competencia;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Competencia>
     */
    public function obtenerPorIds(array $ids): array;

    /**
     * Competencias activas con sus grados, ordenadas por nombre.
     *
     * @return array<int, Competencia>
     */
    public function obtenerActivas(): array;

    /**
     * Crea la competencia `Activo`, con su código y sus cuatro grados.
     *
     * @param  array<string, string>  $grados  Descripción por letra A–D.
     *
     * @throws NombreDuplicado
     */
    public function crear(string $nombre, string $descripcion, TipoCompetencia $tipo, array $grados, ?int $actorId): Competencia;

    /**
     * Reemplaza datos y grados; no cambia código ni estado.
     *
     * @param  array<string, string>  $grados  Descripción por letra A–D.
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     * @throws NombreDuplicado
     */
    public function actualizar(int $id, int $versionEsperada, string $nombre, string $descripcion, TipoCompetencia $tipo, array $grados, ?int $actorId): Competencia;

    /**
     * Cambia el estado y registra el historial; no cambia `version` ni `modificadoPor`.
     *
     * @throws CompetenciaNoEncontrada
     * @throws EstadoSinCambio
     */
    public function cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia;
}
