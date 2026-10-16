<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Operaciones de edición cuya disponibilidad depende del estado del plan. */
enum OperacionPlan
{
    case Editar;
    case CambiarParticipantes;
    case CancelarAccion;
    case ReasignarAprobador;
}
