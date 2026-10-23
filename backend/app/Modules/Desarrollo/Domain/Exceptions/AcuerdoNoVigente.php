<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** La operación exige el acuerdo vigente del responsable y no existe (o ya existe cuando no debía). */
final class AcuerdoNoVigente extends ErrorDePdi
{
    public static function faltante(): self
    {
        return new self('El responsable aún no ha confirmado el acuerdo del plan.', 'acuerdo_no_vigente');
    }

    public static function yaExiste(): self
    {
        return new self('El responsable ya confirmó el acuerdo de este plan.', 'acuerdo_vigente');
    }
}
