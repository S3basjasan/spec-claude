<?php

namespace App\Modules\Desarrollo\Presentation\Facades;

use App\Modules\Desarrollo\Application\Services\DesarrolloService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getOfertaPorCompetencia(int $competenciaId) Cursos y micro-contenidos disponibles de la competencia.
 * @method static bool competenciaTieneOferta(int $competenciaId) Indica si tiene al menos un curso o micro-contenido disponible.
 *
 * @see DesarrolloService
 */
class DesarrolloFacade extends Facade
{
    /**
     * Obtiene el nombre registrado del componente en el Service Container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'desarrollo-facade';
    }
}
