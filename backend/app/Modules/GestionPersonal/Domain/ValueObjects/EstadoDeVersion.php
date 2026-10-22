<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeVersion: string
{
    case Borrador = 'Borrador';
    case Programada = 'Programada';
    case Vigente = 'Vigente';
    case Sustituida = 'Sustituida';
    case Retirada = 'Retirada';
}
