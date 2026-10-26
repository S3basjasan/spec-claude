<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;

interface ContextoDeTenantPort
{
    /** Contexto de tenant de la cuenta autenticada; null si la cuenta no tiene tenant. */
    public function deCuenta(int $userId): ?ContextoDeTenant;

    /** Contexto de un tenant por su identificador, para procesos programados que no tienen cuenta; null si no existe. */
    public function deTenant(int $tenantId): ?ContextoDeTenant;
}
