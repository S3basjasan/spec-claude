<?php

namespace App\Modules\Compensaciones\Infrastructure\Adapters;

use App\Modules\Compensaciones\Application\Ports\NombresDeUsuarioPort;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

/** Obtiene los nombres por la Facade pública de Shared; no lee la tabla de usuarios. */
final class NombresDeUsuarioDesdeShared implements NombresDeUsuarioPort
{
    public function nombresDe(array $usuarioIds): array
    {
        $ids = array_values(array_unique(array_filter($usuarioIds, fn ($id) => $id !== null)));

        return $ids === [] ? [] : SharedFacade::getNombresDeUsuarios($ids);
    }
}
