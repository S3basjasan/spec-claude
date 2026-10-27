<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\PermisoDeEntrevistasPort;

/** Mientras el componente de acceso no defina el permiso, nadie lo posee y las respuestas no se muestran. */
final class PermisoDeEntrevistasNoInstalado implements PermisoDeEntrevistasPort
{
    public function puede(int $userId, int $tenantId): bool
    {
        return false;
    }
}
