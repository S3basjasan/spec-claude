<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ConflictoDeVersionRuta extends ErrorDeRuta
{
    public function __construct(string $mensaje = 'Otro administrador modificó la ruta; recarga para ver los cambios.')
    {
        parent::__construct($mensaje, 'version_desactualizada');
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
