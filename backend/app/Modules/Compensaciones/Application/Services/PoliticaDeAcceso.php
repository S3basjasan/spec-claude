<?php

namespace App\Modules\Compensaciones\Application\Services;

/**
 * Único punto donde se decide quién ve y quién gestiona el catálogo. Hoy solo existen los roles
 * Administrador (capacidad `admin`) y Usuario; cuando existan roles diferenciados se ajusta aquí.
 */
final class PoliticaDeAcceso
{
    /** Cualquier usuario autenticado puede listar y consultar. */
    public function puedeVer(object $usuario): bool
    {
        return true;
    }

    /** Crear, editar, activar y desactivar, ver inactivas y la auditoría: solo el Administrador. */
    public function puedeGestionar(object $usuario): bool
    {
        return method_exists($usuario, 'tokenCan') && (bool) $usuario->tokenCan('admin');
    }
}
