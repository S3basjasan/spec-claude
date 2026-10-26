<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Aprobar una novedad con cruces de periodo sin reconocimiento del Administrador. */
final class CruceSinReconocer extends RuntimeException
{
    public function __construct(string $message = 'El periodo se cruza con otras solicitudes y debe reconocerse antes de aprobar.')
    {
        parent::__construct($message);
    }
}
