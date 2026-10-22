<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Puesto objetivo mal definido: falta, es ambiguo o no existe / no está activo. */
final class ObjetivoInvalido extends ErrorDeAdecuacion
{
    public static function noDefinido(): self
    {
        return new self('Define el puesto objetivo: elige una inscripción o un puesto directo.', 'objetivo_no_definido', 'puesto_objetivo_id');
    }

    public static function ambiguo(): self
    {
        return new self('Elige la inscripción o el puesto directo, no ambos.', 'objetivo_ambiguo', 'puesto_objetivo_id');
    }

    public static function puestoNoDisponible(): self
    {
        return new self('El puesto objetivo no existe o no está activo.', 'objetivo_no_definido', 'puesto_objetivo_id');
    }

    public static function inscripcionAjena(): self
    {
        return new self('La inscripción no es una inscripción Activa del colaborador.', 'objetivo_no_definido', 'inscripcion_id');
    }
}
