<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** No se pudo generar el archivo de exportación; el detalle técnico queda en el registro, no en la respuesta. */
final class ExportacionNoDisponible extends ErrorDeReporte
{
    public function __construct(string $mensaje = 'No fue posible generar el archivo. Intenta de nuevo más tarde.')
    {
        parent::__construct($mensaje, 'exportacion_no_disponible');
    }

    public function estadoHttp(): int
    {
        return 503;
    }
}
