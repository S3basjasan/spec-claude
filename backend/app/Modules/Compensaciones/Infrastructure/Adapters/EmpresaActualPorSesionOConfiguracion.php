<?php

namespace App\Modules\Compensaciones\Infrastructure\Adapters;

use App\Modules\Compensaciones\Application\Ports\EmpresaActualPort;
use App\Modules\Shared\Presentation\Facades\SharedFacade;
use Illuminate\Support\Facades\Auth;

/**
 * Con sesión toma el tenant de la cuenta por el contrato público de Shared. Sin sesión (tareas programadas,
 * comandos) usa `config('compensaciones_prueba.tenant_id')`: PROVISIONAL, solo del repositorio de prueba.
 */
class EmpresaActualPorSesionOConfiguracion implements EmpresaActualPort
{
    public function tenantId(): int
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $contexto = SharedFacade::getContextoDeTenant((int) $usuarioId);

            if ($contexto !== null) {
                return (int) $contexto['tenant_id'];
            }
        }

        return (int) config('compensaciones_prueba.tenant_id', 1);
    }
}
