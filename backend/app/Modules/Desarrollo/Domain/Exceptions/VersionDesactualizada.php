<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class VersionDesactualizada extends ErrorDeActividad
{
    public function __construct(string $mensaje = 'Otro administrador modificó el registro; recarga para ver los cambios.')
    {
        parent::__construct($mensaje, 'version_desactualizada', null);
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
