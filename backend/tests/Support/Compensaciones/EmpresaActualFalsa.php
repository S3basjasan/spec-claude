<?php

namespace Tests\Support\Compensaciones;

use App\Modules\Compensaciones\Application\Ports\EmpresaActualPort;

final class EmpresaActualFalsa implements EmpresaActualPort
{
    public function tenantId(): int
    {
        return 1;
    }
}
