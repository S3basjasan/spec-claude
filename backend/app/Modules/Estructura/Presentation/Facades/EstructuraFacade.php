<?php

namespace App\Modules\Estructura\Presentation\Facades;

use App\Modules\Estructura\Application\Services\EstructuraService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getResumen() Resumen mínimo del módulo.
 * @method static array|null getCompetencia(int $id) Competencia con sus cuatro grados (activa o inactiva), o null.
 * @method static array getCompetenciasPorIds(array $ids) Competencias existentes entre los ids dados, incluidas las inactivas.
 * @method static bool existeCompetencia(int $id) Indica si la competencia existe.
 * @method static bool estaActiva(int $id) Indica si existe y está activa (única válida para nuevas asignaciones).
 * @method static array getCompetenciasActivas() Competencias activas ordenadas por nombre.
 * @method static array|null getComportamientos(int $competenciaId) Comportamientos por grado de una competencia (activa o inactiva), o null.
 * @method static array getComportamientosPorCompetencias(array $competenciaIds) Registros de las competencias indicadas, incluidas las inactivas.
 * @method static bool tieneComportamientosActivos(int $competenciaId) Indica si la competencia está activa y tiene registro (válido para nuevas asignaciones).
 * @method static array getComportamientosActivos() Registros de competencias activas ordenados por nombre.
 * @method static array|null getPreguntas(int $competenciaId) Las cuatro preguntas de una competencia (activa o inactiva), o null.
 * @method static array getPreguntasPorCompetencias(array $competenciaIds) Registros de preguntas de las competencias indicadas, incluidas las inactivas.
 * @method static bool tienePreguntasActivas(int $competenciaId) Indica si la competencia está activa y tiene registro de preguntas (válido para nuevas asignaciones).
 * @method static array getPreguntasActivas() Registros de preguntas de competencias activas ordenados por nombre.
 *
 * @see EstructuraService
 */
class EstructuraFacade extends Facade
{
    /**
     * Obtiene el nombre registrado del componente en el Service Container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'estructura-facade';
    }
}
