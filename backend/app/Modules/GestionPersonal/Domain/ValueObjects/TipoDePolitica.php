<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

/** Tipos de política definidos por el sistema; el tenant no crea otros ni cambia su significado. */
enum TipoDePolitica: string
{
    case Permisos = 'Permisos';
    case Ausencias = 'Ausencias';
    case Licencias = 'Licencias';
    case Vacaciones = 'Vacaciones';
    case Ascensos = 'Ascensos';
    case Traslados = 'Traslados';
    case Offboarding = 'Offboarding';

    /** Identificador en minúsculas usado en las rutas. */
    public function identificador(): string
    {
        return strtolower($this->value);
    }

    public static function desdeIdentificador(string $identificador): ?self
    {
        foreach (self::cases() as $tipo) {
            if ($tipo->identificador() === $identificador) {
                return $tipo;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function identificadores(): array
    {
        return array_map(fn (self $t) => $t->identificador(), self::cases());
    }

    /** `Novedad` | `Movimiento` | `Offboarding` */
    public function categoria(): string
    {
        return match ($this) {
            self::Permisos, self::Ausencias, self::Licencias, self::Vacaciones => 'Novedad',
            self::Ascensos, self::Traslados => 'Movimiento',
            self::Offboarding => 'Offboarding',
        };
    }

    public function aceptaAnticipacion(): bool
    {
        return $this !== self::Ausencias && $this !== self::Offboarding;
    }

    /** Offboarding no tiene un modo «sin nuevas solicitudes»: la salida siempre puede registrarse. */
    public function tieneDisponibilidad(): bool
    {
        return $this !== self::Offboarding;
    }
}
