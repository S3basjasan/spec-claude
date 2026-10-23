<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Evento auditado de un plan. */
enum TipoDeHistorialPdi: string
{
    case Creacion = 'Creacion';
    case Edicion = 'Edicion';
    case Estado = 'Estado';
    case Acuerdo = 'Acuerdo';
    case Aceptacion = 'Aceptacion';
    case Devolucion = 'Devolucion';
    case Responsable = 'Responsable';
    case Vinculo = 'Vinculo';
    case Avance = 'Avance';
    case Hito = 'Hito';
    case Cierre = 'Cierre';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creacion => 'Creación',
            self::Edicion => 'Edición',
            self::Aceptacion => 'Aceptación',
            self::Devolucion => 'Devolución',
            self::Vinculo => 'Vínculo',
            default => $this->value,
        };
    }
}
