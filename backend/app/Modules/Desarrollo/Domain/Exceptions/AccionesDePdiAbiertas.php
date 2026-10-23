<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Quedan acciones Pendientes o En curso que impiden cerrar el plan (FR-032). */
final class AccionesDePdiAbiertas extends ErrorDePdi
{
    public function __construct(public readonly int $cantidad)
    {
        parent::__construct(
            "Para cerrar el plan, todas las acciones deben estar Cumplidas o Canceladas. Faltan {$cantidad}.",
            'acciones_abiertas',
            'acciones_abiertas',
            [],
            ['acciones_abiertas' => $cantidad],
        );
    }
}
