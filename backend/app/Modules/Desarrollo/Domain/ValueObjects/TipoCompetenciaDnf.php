<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Tipo de la competencia al momento del diagnóstico (copia propia, sin depender de Estructura). */
enum TipoCompetenciaDnf: string
{
    case Cardinal = 'Cardinal';
    case Especifica = 'Especifica';
}
