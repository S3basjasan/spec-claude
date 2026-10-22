<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Una escritura necesita datos de otro módulo y su fuente no responde: no se inventa nada, se pide reintentar. */
final class FuenteNoDisponibleParaRuta extends ErrorDeRuta
{
    public function __construct(string $fuente = 'Estructura Empresarial')
    {
        parent::__construct("No se pudo consultar {$fuente}. Inténtalo de nuevo.", 'fuente_no_disponible');
    }

    public function estadoHttp(): int
    {
        return 503;
    }
}
