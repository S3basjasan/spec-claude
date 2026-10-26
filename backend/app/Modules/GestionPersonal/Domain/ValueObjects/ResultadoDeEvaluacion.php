<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

/** Resultado de consultar la evaluación del colaborador frente al puesto destino (nunca una puntuación). */
enum ResultadoDeEvaluacion: string
{
    case Valida = 'Valida';
    case NoDisponible = 'NoDisponible';
    case NoVerificable = 'NoVerificable';
}
