<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum TipoDeHecho: string
{
    case Ingreso = 'Ingreso';
    case Reingreso = 'Reingreso';
    case Ascenso = 'Ascenso';
    case Traslado = 'Traslado';
    case Salida = 'Salida';
}
