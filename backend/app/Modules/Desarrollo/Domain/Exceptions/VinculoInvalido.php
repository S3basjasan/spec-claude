<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** La acción del Plan de Formación no se puede vincular (FR-018, FR-019). */
final class VinculoInvalido extends ErrorDePdi
{
    public static function tipoNoVinculable(): self
    {
        return new self('Solo las acciones de tipo Curso, Mentoring o Codesarrollo se pueden vincular al Plan de Formación.', 'vinculo_invalido', 'plan_accion_id');
    }

    public static function noDisponible(): self
    {
        return new self('La acción del Plan de Formación no está disponible para este colaborador.', 'vinculo_invalido', 'plan_accion_id');
    }
}
