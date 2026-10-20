<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class AsistenciaNoRegistrable extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'Solo se puede registrar asistencia en actividades en curso o finalizadas.')
    {
        parent::__construct($mensaje, 'asistencia_no_registrable', null);
    }
}
