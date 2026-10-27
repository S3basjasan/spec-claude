<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeSalida: string
{
    case Programada = 'Programada';
    case Efectiva = 'Efectiva';
    case Cancelada = 'Cancelada';
    case IncidenciaDeAplicacion = 'IncidenciaDeAplicacion';

    /** Aún no se aplicó y puede cancelarse. */
    public function esPendienteDeAplicar(): bool
    {
        return $this === self::Programada || $this === self::IncidenciaDeAplicacion;
    }
}
