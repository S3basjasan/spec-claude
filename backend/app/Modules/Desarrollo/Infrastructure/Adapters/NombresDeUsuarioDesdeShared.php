<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

/** Obtiene los nombres por la Facade publica de Shared; no lee la tabla de usuarios. */
final class NombresDeUsuarioDesdeShared implements NombresDeUsuarioInterface
{
    public function nombresDe(array $usuarioIds): array
    {
        $ids = array_values(array_unique(array_filter($usuarioIds, fn ($id) => $id !== null)));

        return $ids === [] ? [] : SharedFacade::getNombresDeUsuarios($ids);
    }
}
