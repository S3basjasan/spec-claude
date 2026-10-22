<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class RegistroDeAsistenciaInvalido extends ErrorDeActividad
{
    /**
     * @param  array<string, list<string>>  $errores
     */
    public function __construct(array $errores, string $mensaje = 'Los registros de asistencia no son válidos.')
    {
        parent::__construct($mensaje, 'registro_invalido', null, $errores);
    }
}
