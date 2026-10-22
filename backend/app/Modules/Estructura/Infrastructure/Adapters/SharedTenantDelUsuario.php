<?php

namespace App\Modules\Estructura\Infrastructure\Adapters;

use App\Modules\Estructura\Application\Ports\TenantDelUsuarioInterface;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

/**
 * Adaptador del puerto sobre la Facade pública del módulo Shared.
 */
final class SharedTenantDelUsuario implements TenantDelUsuarioInterface
{
    public function tenantDe(int $userId): ?int
    {
        $contexto = SharedFacade::getContextoDeTenant($userId);

        return $contexto === null ? null : (int) $contexto['tenant_id'];
    }
}
