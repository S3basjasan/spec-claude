<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

/** Obtiene el tenant de la cuenta por el contrato público de Shared. */
final class SharedContextoDeTenant implements ContextoDeTenantPort
{
    public function deCuenta(int $userId): ?ContextoDeTenant
    {
        $contexto = SharedFacade::getContextoDeTenant($userId);

        if ($contexto === null) {
            return null;
        }

        return new ContextoDeTenant($contexto['tenant_id'], $contexto['nombre'], $contexto['zona_horaria']);
    }

    public function deTenant(int $tenantId): ?ContextoDeTenant
    {
        $contexto = SharedFacade::getContextoDeTenantPorId($tenantId);

        if ($contexto === null) {
            return null;
        }

        return new ContextoDeTenant($contexto['tenant_id'], $contexto['nombre'], $contexto['zona_horaria']);
    }
}
