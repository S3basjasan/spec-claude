<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class MicroConProgreso extends ErrorDeMicro
{
    public function __construct()
    {
        parent::__construct('El micro-contenido ya tiene progreso de colaboradores: solo puede archivarse.', 'con_progreso');
    }
}
