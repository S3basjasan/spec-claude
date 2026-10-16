<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class PlanNoEditable extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('El plan no se puede modificar en su estado actual.', 'plan_no_editable');
    }
}
