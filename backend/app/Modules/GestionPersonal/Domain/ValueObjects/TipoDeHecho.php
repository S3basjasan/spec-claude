<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum TipoDeHecho: string
{
    case Ingreso = 'Ingreso';
    case Reingreso = 'Reingreso';
}
