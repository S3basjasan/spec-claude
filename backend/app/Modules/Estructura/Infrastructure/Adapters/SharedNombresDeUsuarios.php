<?php

namespace App\Modules\Estructura\Infrastructure\Adapters;

use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

/**
 * Adaptador del puerto sobre la Facade pública del módulo Shared.
 */
final class SharedNombresDeUsuarios implements NombresDeUsuariosInterface
{
    public function nombres(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));

        return $ids === [] ? [] : SharedFacade::getNombresDeUsuarios($ids);
    }

    public function idsPorNombre(string $texto): array
    {
        return trim($texto) === '' ? [] : SharedFacade::buscarIdsDeUsuariosPorNombre($texto);
    }
}
