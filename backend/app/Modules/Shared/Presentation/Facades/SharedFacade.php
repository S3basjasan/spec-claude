<?php

namespace App\Modules\Shared\Presentation\Facades;

use App\Modules\Shared\Application\Services\SharedService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getResumen() Resumen mínimo del módulo.
 * @method static array getNombresDeUsuarios(array $ids) Nombres de usuario por id (id => nombre); omite los ids que no existen.
 * @method static array buscarIdsDeUsuariosPorNombre(string $texto) Ids de los usuarios cuyo nombre contiene el texto (sin tildes ni mayúsculas, máximo 200, por nombre).
 * @method static array getNombresDeUsuarios(array $ids) Nombres [id => nombre] de los usuarios existentes entre los ids dados.
 * @method static array getAdministradores(int $tenantId) Usuarios con rol Administrador del tenant [{id, nombre}], por nombre.
 * @method static array|null getContextoDeTenant(int $userId) {tenant_id, nombre, zona_horaria} de la cuenta, o null.
 * @method static array|null getContextoDeTenantPorId(int $tenantId) {tenant_id, nombre, zona_horaria} del tenant, o null (procesos programados sin cuenta).
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
