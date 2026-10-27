<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

/** Clasificación administrativa formal de la desvinculación (no equivale a los motivos declarados por el colaborador). */
enum MotivoDeSalida: string
{
    case Renuncia = 'Renuncia';
    case Terminacion = 'Terminacion';
    case FinContrato = 'FinContrato';
    case Jubilacion = 'Jubilacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Renuncia => 'Renuncia voluntaria',
            self::Terminacion => 'Terminación por decisión de la organización',
            self::FinContrato => 'Finalización de contrato',
            self::Jubilacion => 'Jubilación',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
