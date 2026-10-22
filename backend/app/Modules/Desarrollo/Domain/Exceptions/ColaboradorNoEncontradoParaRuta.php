<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ColaboradorNoEncontradoParaRuta extends ErrorDeRuta
{
    public function __construct(string $mensaje = 'El colaborador no existe.')
    {
        parent::__construct($mensaje, 'colaborador_no_encontrado');
    }

    public function estadoHttp(): int
    {
        return 404;
    }
}
