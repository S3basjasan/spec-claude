<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class FuenteNoDisponibleParaAdecuacion extends ErrorDeAdecuacion
{
    public function __construct()
    {
        parent::__construct('No se pudo leer la información necesaria. Intenta de nuevo en unos minutos.', 'fuente_no_disponible');
    }

    public function estadoHttp(): int
    {
        return 503;
    }
}
