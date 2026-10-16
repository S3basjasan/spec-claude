<?php

namespace App\Modules\Shared\Presentation\Facades;

use App\Modules\Shared\Application\Services\SharedService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getResumen() Resumen mínimo del módulo.
 * @method static array getNombresDeUsuarios(array $ids) Nombres de usuario por id (id => nombre); omite los ids que no existen.
 * @method static array buscarIdsDeUsuariosPorNombre(string $texto) Ids de los usuarios cuyo nombre contiene el texto (sin tildes ni mayúsculas, máximo 200, por nombre).
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
