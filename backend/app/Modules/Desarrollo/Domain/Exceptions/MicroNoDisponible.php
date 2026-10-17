<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El contenido existe pero no se puede iniciar ni avanzar (no está Publicado) o no admite la operación pedida. */
final class MicroNoDisponible extends ErrorDeMicro
{
    public function __construct(string $mensaje = 'El micro-contenido no está disponible.')
    {
        parent::__construct($mensaje, 'no_disponible');
    }
}
