<?php

namespace App\Modules\Compensaciones\Application\Ports;

/** Empresa (tenant) en la que se ejecuta la operación actual. */
interface EmpresaActualPort
{
    public function tenantId(): int;
}
