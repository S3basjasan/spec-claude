<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Clasificación del sistema sobre el resultado completo de una adecuación (FR-016). */
enum Preparacion: string
{
    case Lista = 'Lista';
    case EnDesarrollo = 'EnDesarrollo';
    case NoLista = 'NoLista';
    case Incompleta = 'Incompleta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Lista => 'Lista',
            self::EnDesarrollo => 'En desarrollo',
            self::NoLista => 'No lista',
            self::Incompleta => 'Incompleta',
        };
    }
}
