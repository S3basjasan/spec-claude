<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\NombreDeColaboradorInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Nombre del colaborador obtenido solo por la Facade pública de Gestión de Personal. */
final class NombreDeColaboradorDesdePersonal implements NombreDeColaboradorInterface
{
    public function nombreDe(int $tenantId, int $colaboradorId): ?string
    {
        $ficha = PersonalFacade::getColaborador($tenantId, $colaboradorId);
        if ($ficha === null) {
            return null;
        }

        $nombre = trim(($ficha['nombres'] ?? '').' '.($ficha['apellidos'] ?? ''));

        return $nombre === '' ? null : $nombre;
    }
}
