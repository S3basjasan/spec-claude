<?php

namespace App\Modules\Shared\Presentation\Facades;

use App\Modules\Shared\Application\Services\SharedService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getResumen() Resumen mínimo del módulo.
 * @method static array getNombresDeUsuarios(array $ids) Nombres [id => nombre] de los usuarios existentes entre los ids dados.
 *
 * @see SharedService
 */
class SharedFacade extends Facade
{
    /**
     * Obtiene el nombre registrado del componente en el Service Container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'shared-facade';
    }
}
