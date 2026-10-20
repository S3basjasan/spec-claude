<?php

namespace App\Modules\Compensaciones\Presentation\Facades;

use App\Modules\Compensaciones\Application\Services\CompensacionesService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getResumen() Resumen mínimo del módulo.
 *
 * @see CompensacionesService
 */
class CompensacionesFacade extends Facade
{
    /**
     * Obtiene el nombre registrado del componente en el Service Container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'compensaciones-facade';
    }
}
