<?php

namespace App\Modules\Compensaciones\Application\Services;

/**
 * Único punto donde se decide quién gestiona el catálogo de categorías. Los permisos se nombran
 * recurso.acción; hoy solo existe `categorias.gestionar` (`parametros.gestionar` llega en 01b.1 y
 * las specs siguientes agregan los suyos). Ver categorías no lleva permiso: puede cualquier usuario
 * autenticado.
 *
 * En el repositorio de prueba el permiso del Gestor de Talento se resuelve con la capacidad `admin`
 * del token del rol Administrador existente; no se crea ni se renombra ningún rol.
 */
final class PoliticaDeAcceso
{
    /** Crear, editar, activar y desactivar, ver inactivas y la auditoría: solo el Gestor de Talento. */
    public const CATEGORIAS_GESTIONAR = 'categorias.gestionar';

    /** Un permiso desconocido se deniega siempre. */
    public function puede(object $usuario, string $permiso): bool
    {
        return match ($permiso) {
            self::CATEGORIAS_GESTIONAR => method_exists($usuario, 'tokenCan') && (bool) $usuario->tokenCan('admin'),
            default => false,
        };
    }
}
