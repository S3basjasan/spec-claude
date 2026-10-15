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
 * @method static array getTodasLasCompetencias() Todas las competencias (activas e inactivas) {id, codigo, nombre, descripcion, tipo, estado}, sin grados, por nombre.
 * @method static array|null getComportamientos(int $competenciaId) Comportamientos por grado de una competencia (activa o inactiva), o null.
 * @method static array getComportamientosPorCompetencias(array $competenciaIds) Registros de las competencias indicadas, incluidas las inactivas.
 * @method static bool tieneComportamientosActivos(int $competenciaId) Indica si la competencia está activa y tiene registro (válido para nuevas asignaciones).
 * @method static array getComportamientosActivos() Registros de competencias activas ordenados por nombre.
 * @method static array|null getPreguntas(int $competenciaId) Las cuatro preguntas de una competencia (activa o inactiva), o null.
 * @method static array getPreguntasPorCompetencias(array $competenciaIds) Registros de preguntas de las competencias indicadas, incluidas las inactivas.
 * @method static bool tienePreguntasActivas(int $competenciaId) Indica si la competencia está activa y tiene registro de preguntas (válido para nuevas asignaciones).
 * @method static array getPreguntasActivas() Registros de preguntas de competencias activas ordenados por nombre.
 * @method static array|null getPuesto(int $id) Puesto con sus funciones, requisitos y competencias (activo o inactivo), o null.
 * @method static array getPuestosPorIds(array $ids) Puestos existentes entre los ids dados, incluidos los inactivos.
 * @method static bool existePuesto(int $id) Indica si el puesto existe (activo o inactivo).
 * @method static bool puestoEstaActivo(int $id) Indica si existe y está activo (único válido para crear nuevas posiciones).
 * @method static array getPuestosActivos() Puestos activos {id, codigo, nombre, descripcion} ordenados por nombre.
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
