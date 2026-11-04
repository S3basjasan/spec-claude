<?php

namespace App\Modules\Compensaciones\Application\Services;

/**
 * Único punto donde se decide quién gestiona el catálogo de categorías. Los permisos se nombran
 * recurso.acción; hoy existen `categorias.gestionar` y `parametros.gestionar` (las specs siguientes agregan los suyos). Ver categorías no lleva permiso: puede cualquier usuario
 * autenticado.
 *
 * En el repositorio de prueba el permiso del Gestor de Talento se resuelve con la capacidad `admin`
 * del token del rol Administrador existente; no se crea ni se renombra ningún rol.
 */
final class PoliticaDeAcceso
{
    /** Crear, editar, activar y desactivar, ver inactivas y la auditoría: solo el Gestor de Talento. */
    public const CATEGORIAS_GESTIONAR = 'categorias.gestionar';

    /** Ver y cambiar los parámetros del módulo: solo el Gestor de Talento. */
    public const PARAMETROS_GESTIONAR = 'parametros.gestionar';

    /** Un permiso desconocido se deniega siempre. */
    public function puede(object $usuario, string $permiso): bool
    {
        return match ($permiso) {
            self::CATEGORIAS_GESTIONAR, self::PARAMETROS_GESTIONAR => method_exists($usuario, 'tokenCan') && (bool) $usuario->tokenCan('admin'),
            default => false,
        };
    }
}
