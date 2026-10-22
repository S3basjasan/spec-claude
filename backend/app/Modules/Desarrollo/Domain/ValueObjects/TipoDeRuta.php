<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Tipo de ruta de carrera (se guarda sin tildes; la interfaz muestra «Interáreas» y «Jóvenes profesionales»). */
enum TipoDeRuta: string
{
    case Especialista = 'Especialista';
    case Gerencial = 'Gerencial';
    case Interareas = 'Interareas';
    case JovenesProfesionales = 'JovenesProfesionales';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Interareas => 'Interáreas',
            self::JovenesProfesionales => 'Jóvenes profesionales',
            default => $this->value,
        };
    }

    /** Las rutas Interáreas cruzan departamentos: no nacen en uno solo. */
    public function admiteDepartamento(): bool
    {
        return $this !== self::Interareas;
    }
}
