<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class NombreDePlanDuplicado extends ErrorDePlan
{
    public function __construct()
    {
        parent::__construct('Ya existe un plan con ese nombre en el año.', 'nombre_duplicado', 'nombre');
    }
}
