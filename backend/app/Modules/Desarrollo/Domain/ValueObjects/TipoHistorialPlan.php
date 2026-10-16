<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Clase de renglón del historial de un plan. */
enum TipoHistorialPlan: string
{
    case Estado = 'Estado';
    case Participante = 'Participante';
    case Cancelacion = 'Cancelacion';
    case Aprobador = 'Aprobador';
}
