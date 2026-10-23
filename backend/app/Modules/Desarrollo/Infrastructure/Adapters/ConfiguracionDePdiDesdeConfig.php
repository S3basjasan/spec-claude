<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\ConfiguracionDePdiInterface;
use LogicException;

/** Lee la bandera `desarrollo.pdi.conocimientos_con_grado`; su rama `true` no está implementada (research R5). */
final class ConfiguracionDePdiDesdeConfig implements ConfiguracionDePdiInterface
{
    public function conocimientosConGrado(): bool
    {
        if (config('desarrollo.pdi.conocimientos_con_grado', false) === true) {
            throw new LogicException('Los conocimientos con grado no están implementados: la bandera desarrollo.pdi.conocimientos_con_grado debe ser false.');
        }

        return false;
    }
}
