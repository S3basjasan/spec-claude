<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

/** Obtiene los administradores por la Facade pública de Shared; no lee la tabla de usuarios. */
final class AprobadoresDesdeShared implements AprobadoresInterface
{
    public function administradores(int $tenantId): array
    {
        return SharedFacade::getAdministradores($tenantId);
    }

    public function esAdministradorActivo(int $tenantId, int $usuarioId): bool
    {
        foreach ($this->administradores($tenantId) as $administrador) {
            if ($administrador['id'] === $usuarioId) {
                return true;
            }
        }

        return false;
    }
}
