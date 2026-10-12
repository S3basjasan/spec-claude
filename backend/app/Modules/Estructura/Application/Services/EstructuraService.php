<?php

namespace App\Modules\Estructura\Application\Services;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;
use App\Modules\Estructura\Domain\Entities\RegistroPreguntas;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;

/**
 * Servicio inter-módulos del Módulo Estructura.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Es la única puerta de los demás módulos hacia el diccionario de competencias
 * (ver specs/001-diccionario-competencias/contracts/estructura-facade.md).
 */
class EstructuraService
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly ComportamientoRepositoryInterface $comportamientos,
        private readonly PreguntaRepositoryInterface $preguntas,
    ) {}

    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => 'Estructura'];
    }

    /**
     * Competencia con sus cuatro grados, activa o inactiva.
     *
     * @return array<string, mixed>|null
     */
    public function getCompetencia(int $id): ?array
    {
        $competencia = $this->competencias->obtener($id);

        return $competencia === null ? null : $this->aArreglo($competencia);
    }

    /**
     * Competencias existentes entre los ids dados, incluidas las inactivas (registros históricos).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function getCompetenciasPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map($this->aArreglo(...), $this->competencias->obtenerPorIds(array_values(array_unique($ids))));
    }

    public function existeCompetencia(int $id): bool
    {
        return $this->competencias->obtener($id) !== null;
    }

    /** Solo las competencias activas pueden asignarse en nuevos registros. */
    public function estaActiva(int $id): bool
    {
        return $this->competencias->obtener($id)?->estaActiva() ?? false;
    }

    /**
     * Competencias activas ordenadas por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCompetenciasActivas(): array
    {
        return array_map($this->aArreglo(...), $this->competencias->obtenerActivas());
    }

    /**
     * Registro de comportamientos de una competencia (activa o inactiva, para registros históricos), o null si no tiene.
     *
     * @return array<string, mixed>|null
     */
    public function getComportamientos(int $competenciaId): ?array
    {
        $registro = $this->comportamientos->obtener($competenciaId);

        return $registro === null ? null : $this->registroAArreglo($registro);
    }

    /**
     * Registros de las competencias indicadas que tienen comportamientos, incluidas las inactivas.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, mixed>>
     */
    public function getComportamientosPorCompetencias(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        return array_map($this->registroAArreglo(...), $this->comportamientos->obtenerPorCompetencias(array_values(array_unique($competenciaIds))));
    }

    /** Solo los comportamientos de competencias activas pueden asignarse en nuevos registros. */
    public function tieneComportamientosActivos(int $competenciaId): bool
    {
        return $this->comportamientos->obtener($competenciaId)?->competenciaActiva() ?? false;
    }

    /**
     * Registros de competencias activas ordenados por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getComportamientosActivos(): array
    {
        return array_map($this->registroAArreglo(...), $this->comportamientos->obtenerActivos());
    }

    /**
     * Registro de preguntas de una competencia (activa o inactiva, para registros históricos), o null si no tiene.
     *
     * @return array<string, mixed>|null
     */
    public function getPreguntas(int $competenciaId): ?array
    {
        $registro = $this->preguntas->obtener($competenciaId);

        return $registro === null ? null : $this->preguntasAArreglo($registro);
    }

    /**
     * Registros de preguntas de las competencias indicadas que tienen registro, incluidas las inactivas.
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, mixed>>
     */
    public function getPreguntasPorCompetencias(array $competenciaIds): array
    {
        if ($competenciaIds === []) {
            return [];
        }

        return array_map($this->preguntasAArreglo(...), $this->preguntas->obtenerPorCompetencias(array_values(array_unique($competenciaIds))));
    }

    /** Solo las preguntas de competencias activas pueden asignarse en nuevos registros. */
    public function tienePreguntasActivas(int $competenciaId): bool
    {
        return $this->preguntas->obtener($competenciaId)?->competenciaActiva() ?? false;
    }

    /**
     * Registros de preguntas de competencias activas ordenados por nombre, para nuevas asignaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPreguntasActivas(): array
    {
        return array_map($this->preguntasAArreglo(...), $this->preguntas->obtenerActivos());
    }

    /**
     * @return array<string, mixed>
     */
    private function preguntasAArreglo(RegistroPreguntas $registro): array
    {
        $competencia = $registro->competencia;

        return [
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'preguntas' => $registro->preguntas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registroAArreglo(RegistroComportamientos $registro): array
    {
        $competencia = $registro->competencia;

        return [
            'competencia_id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'comportamientos' => $registro->comportamientos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aArreglo(Competencia $competencia): array
    {
        return [
            'id' => $competencia->id,
            'codigo' => $competencia->codigo,
            'nombre' => $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'tipo' => $competencia->tipo->value,
            'estado' => $competencia->estado->value,
            'grados' => $competencia->grados,
        ];
    }
}
