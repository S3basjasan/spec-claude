<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;

/**
 * Adaptador PROVISIONAL: la DNF aun no existe, por lo que no hay brechas activas.
 * Al implementarse la DNF, se reemplaza por un adaptador que llame a su Facade y se cambia el binding.
 */
final class BrechasActivasSinDatos implements BrechasActivasInterface
{
    public function brechasDe(int $usuarioId): array
    {
        return [];
    }
}
