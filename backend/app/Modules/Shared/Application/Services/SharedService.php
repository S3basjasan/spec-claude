<?php

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;

/**
 * Servicio inter-módulos del Módulo Shared.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Expande con métodos get* que delegan a Application\UseCases.
 */
class SharedService
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios) {}

    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => 'Shared'];
    }

    /**
     * Nombres de usuario por id, para que otros módulos muestren quién hizo un cambio sin leer la tabla de usuarios.
     * Solo devuelve id y nombre; los ids que no existen se omiten.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function getNombresDeUsuarios(array $ids): array
    {
        return $this->usuarios->nombresPorIds($ids);
    }
}
