<?php

namespace App\Modules\Estructura\Application\Ports;

/**
 * Puerto para saber a qué tenant pertenece la persona autenticada. Estructura no lee la tabla de usuarios: el adaptador
 * usa el contrato público del módulo Shared.
 */
interface TenantDelUsuarioInterface
{
    /** Id del tenant de la cuenta, o nulo si la cuenta no existe. */
    public function tenantDe(int $userId): ?int;
}
