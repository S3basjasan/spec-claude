<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\RegistroPreguntas;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTienePreguntas;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaRegistrosPreguntas;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

/**
 * Puerto de persistencia de los registros de preguntas. Los datos de la competencia (código, nombre,
 * descripción, tipo y estado) se leen siempre en vivo; el registro no los almacena.
 */
interface PreguntaRepositoryInterface
{
    /** Registros de competencias que tienen preguntas, sin los textos, ordenados por nombre de la competencia. */
    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaRegistrosPreguntas;

    /** Registro con sus cuatro preguntas (competencia activa o inactiva), o null si no tiene. */
    public function obtener(int $competenciaId): ?RegistroPreguntas;

    /**
     * @param  array<int, int>  $competenciaIds
     * @return array<int, RegistroPreguntas>
     */
    public function obtenerPorCompetencias(array $competenciaIds): array;

    /** @return array<int, RegistroPreguntas> De competencias activas, ordenados por nombre. */
    public function obtenerActivos(): array;

    /** @return array<int, CompetenciaAsociada> Competencias activas sin registro, ordenadas por nombre. */
    public function disponibles(): array;

    /**
     * @param  array<int, string>  $preguntas  Exactamente cuatro textos recortados.
     *
     * @throws CompetenciaNoDisponible
     * @throws CompetenciaYaTienePreguntas
     */
    public function crear(int $competenciaId, array $preguntas, ?int $actorId): RegistroPreguntas;

    /**
     * Sin cambios reales no escribe nada (ni versión ni auditoría).
     *
     * @param  array<int, string>  $preguntas  Exactamente cuatro textos recortados.
     *
     * @throws PreguntasNoEncontradas
     * @throws RegistroModificado
     */
    public function actualizar(int $competenciaId, int $versionEsperada, array $preguntas, ?int $actorId): RegistroPreguntas;
}
