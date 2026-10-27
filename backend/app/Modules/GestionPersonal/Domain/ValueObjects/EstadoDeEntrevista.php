<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeEntrevista: string
{
    case Pendiente = 'Pendiente';
    case EnCurso = 'EnCurso';
    case Completada = 'Completada';
    case NoRealizada = 'NoRealizada';

    /** Completada o «no realizada» con motivo: la tarea de entrevista cuenta como resuelta. */
    public function estaResuelta(): bool
    {
        return $this === self::Completada || $this === self::NoRealizada;
    }
}
