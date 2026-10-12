<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaRegistrosComportamientos;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

interface ComportamientoRepositoryInterface
{
    /** Registros ordenados por nombre de la competencia (A–Z), sin comportamientos. `$estado` nulo = todos. */
    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaRegistrosComportamientos;

    /** Registro con sus comportamientos (de competencia activa o inactiva), o null si no existe. */
    public function obtener(int $competenciaId): ?RegistroComportamientos;

    /**
     * @param  array<int, int>  $competenciaIds
     * @return array<int, RegistroComportamientos>
     */
    public function obtenerPorCompetencias(array $competenciaIds): array;

    /**
     * Registros de competencias activas, con sus comportamientos, ordenados por nombre.
     *
     * @return array<int, RegistroComportamientos>
     */
    public function obtenerActivos(): array;

    /**
     * Competencias activas que todavía no tienen registro, ordenadas por nombre.
     *
     * @return array<int, CompetenciaAsociada>
     */
    public function disponibles(): array;

    /**
     * @param  array<string, array<int, string>>  $comportamientos  Textos por letra A–D (1 a 4 por letra).
     *
     * @throws CompetenciaNoDisponible
     * @throws CompetenciaYaTieneRegistro
     */
    public function crear(int $competenciaId, array $comportamientos, ?int $actorId): RegistroComportamientos;

    /**
     * Reemplaza los comportamientos. Si no hay cambios reales no modifica nada (ni versión ni auditoría).
     *
     * @param  array<string, array<int, string>>  $comportamientos
     *
     * @throws RegistroNoEncontrado
     * @throws RegistroModificado
     */
    public function actualizar(int $competenciaId, int $versionEsperada, array $comportamientos, ?int $actorId): RegistroComportamientos;
}
