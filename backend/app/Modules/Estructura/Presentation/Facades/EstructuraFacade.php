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
 * @method static array|null getPuesto(int $id) Puesto con sus funciones, requisitos y competencias (activo o inactivo), o null.
 * @method static array getPuestosPorIds(array $ids) Puestos existentes entre los ids dados, incluidos los inactivos.
 * @method static bool existePuesto(int $id) Indica si el puesto existe (activo o inactivo).
 * @method static bool puestoEstaActivo(int $id) Indica si existe y está activo (único válido para crear nuevas posiciones).
 * @method static array getPuestosActivos() Puestos activos {id, codigo, nombre, descripcion} ordenados por nombre.
 * @method static array|null getUnidad(int $id) Unidad (activa o inactiva) con su tipo, unidad padre y nivel, o null.
 * @method static array getUnidadesPorIds(array $ids) Unidades existentes entre los ids dados, incluidas las inactivas.
 * @method static bool existeUnidad(int $id) Indica si la unidad existe (activa o inactiva).
 * @method static bool unidadEstaActiva(int $id) Indica si existe y está activa (única válida para nuevas posiciones y unidades dependientes).
 * @method static array getUnidadesActivas() Unidades activas {id, codigo, nombre, tipo, nivel} en orden jerárquico.
 * @method static array getArbolDeUnidades() Todas las unidades (activas e inactivas) en orden jerárquico, con su nivel.
 * @method static array getCadenaDeTipos() Cadena de tipos de unidad {id, nombre, nivel, es_raiz} por nivel (el 1 es el tipo raíz).
 * @method static array|null getTipoDeUnidad(int $id) Tipo de unidad {id, nombre, nivel, es_raiz}, o null.
 * @method static array|null getPosicion(int $id) Posición (también Suprimida) con su puesto, unidad, supervisor y ocupante_id, o null.
 * @method static array getPosicionesPorIds(array $ids) Posiciones existentes entre los ids dados, incluidas las Suprimidas.
 * @method static bool existePosicion(int $id) Indica si la posición existe (también Suprimida).
 * @method static bool posicionEstaVigente(int $id) Indica si existe y está Ocupada o Vacante.
 * @method static array getArbolDeSupervision() Posiciones no Suprimidas {id, codigo, estado, supervisor_id} para el organigrama.
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
