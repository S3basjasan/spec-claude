<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaSinCambio;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaCompetencias;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

interface CompetenciaRepositoryInterface
{
    /** Listado ordenado por código; `$estado` nulo = activas e inactivas. `$q` se compara con el nombre normalizado. */
    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaCompetencias;

    /** Competencia con sus grados, comportamientos, preguntas y categorías (activa o inactiva), o null si no existe. */
    public function obtener(int $id): ?Competencia;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Competencia>
     */
    public function obtenerPorIds(array $ids): array;

    /**
     * Competencias activas con todo su contenido, ordenadas por nombre.
     *
     * @return array<int, Competencia>
     */
    public function obtenerActivas(): array;

    /**
     * Todas las competencias, activas e inactivas, ordenadas por nombre.
     *
     * @return array<int, Competencia>
     */
    public function obtenerTodas(): array;

    /**
     * Crea la competencia `Activo` con su código, 4 grados, 16 comportamientos, 4 preguntas y, si se indican,
     * los nombres propios de las categorías, todo en una transacción.
     *
     * @param  array<int, string>  $grados  4 descripciones, de la más alta a la más básica.
     * @param  array<int, array<int, string>>  $comportamientos  4 listas de 4 textos (una por grado).
     * @param  array<int, string>  $preguntas  4 textos.
     * @param  array<int, string>|null  $categorias  4 nombres propios o null.
     *
     * @throws NombreDuplicado
     * @throws EscalaNoEncontrada
     */
    public function crear(
        string $nombre,
        string $descripcion,
        TipoCompetencia $tipo,
        int $escalaId,
        ?array $categorias,
        array $grados,
        array $comportamientos,
        array $preguntas,
        ?int $actorId,
    ): Competencia;

    /**
     * Reemplaza datos, escala, grados y (si se envían) las categorías; no cambia código ni estado. Sube la versión.
     *
     * @param  array<int, string>  $grados  4 descripciones.
     * @param  array<int, string>|null  $categorias  4 nombres propios o null para no tocar los guardados.
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     * @throws NombreDuplicado
     * @throws EscalaNoEncontrada
     */
    public function actualizar(
        int $id,
        int $versionEsperada,
        string $nombre,
        string $descripcion,
        TipoCompetencia $tipo,
        int $escalaId,
        ?array $categorias,
        array $grados,
        ?int $actorId,
    ): Competencia;

    /**
     * Actualiza (o completa) los 16 comportamientos. Sube la versión.
     *
     * @param  array<int, array<int, string>>  $comportamientos  4 listas de 4 textos.
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     */
    public function actualizarComportamientos(int $id, int $versionEsperada, array $comportamientos, ?int $actorId): Competencia;

    /**
     * Actualiza (o completa) las 4 preguntas. Sube la versión.
     *
     * @param  array<int, string>  $preguntas
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     */
    public function actualizarPreguntas(int $id, int $versionEsperada, array $preguntas, ?int $actorId): Competencia;

    /**
     * Cambia solo los nombres de los grados (mismas filas), registra el historial de escala y sube la versión.
     *
     * @throws CompetenciaNoEncontrada
     * @throws EscalaNoEncontrada
     * @throws EscalaSinCambio
     */
    public function aplicarEscala(int $id, int $escalaId, ?int $actorId): Competencia;

    /**
     * Cambia el estado, registra el historial y sube la versión.
     *
     * @throws CompetenciaNoEncontrada
     * @throws EstadoSinCambio
     */
    public function cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia;
}
