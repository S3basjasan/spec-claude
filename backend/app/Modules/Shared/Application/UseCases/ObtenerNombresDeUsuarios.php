<?php

namespace App\Modules\Shared\Application\UseCases;

use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;

/**
 * Devuelve solo los nombres de los usuarios pedidos, para que otros módulos muestren quién hizo un cambio
 * sin acceder a la tabla de usuarios. No expone correo, rol ni contraseña.
 */
final class ObtenerNombresDeUsuarios
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios) {}

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string> id => nombre, solo de los usuarios que existen
     */
    public function ejecutar(array $ids): array
    {
        return $this->usuarios->nombresPorIds($ids);
    }
}
