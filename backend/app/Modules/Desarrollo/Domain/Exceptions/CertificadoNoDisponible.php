<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** No se pudo generar el PDF del certificado: es un fallo del servicio, no un error de la petición. */
final class CertificadoNoDisponible extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct('No se pudo generar el certificado. Inténtalo de nuevo.', 'certificado_no_disponible');
    }

    public function estadoHttp(): int
    {
        return 503;
    }
}
