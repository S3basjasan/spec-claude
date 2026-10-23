<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Configuración del PDI que decide qué dimensiones están disponibles. */
interface ConfiguracionDePdiInterface
{
    /** Con `false` la dimensión Conocimiento se desactiva en los compromisos. */
    public function conocimientosConGrado(): bool;
}
