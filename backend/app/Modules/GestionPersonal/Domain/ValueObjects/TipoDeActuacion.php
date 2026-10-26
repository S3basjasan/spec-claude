<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

/** Tipos de actuación definidos por el sistema; cada uno consume la política del mismo nombre. */
enum TipoDeActuacion: string
{
    case Ascenso = 'Ascenso';
    case Traslado = 'Traslado';
    case Permiso = 'Permiso';
    case Licencia = 'Licencia';
    case Vacaciones = 'Vacaciones';
    case Ausencia = 'Ausencia';

    public function esMovimiento(): bool
    {
        return $this === self::Ascenso || $this === self::Traslado;
    }

    public function esAusencia(): bool
    {
        return $this === self::Ausencia;
    }

    /** Permiso, licencia o vacaciones: las novedades que se solicitan y se resuelven. */
    public function esSolicitudDeNovedad(): bool
    {
        return $this === self::Permiso || $this === self::Licencia || $this === self::Vacaciones;
    }

    /** `Movimiento` | `Novedad` */
    public function categoria(): string
    {
        return $this->esMovimiento() ? 'Movimiento' : 'Novedad';
    }

    public function politica(): TipoDePolitica
    {
        return match ($this) {
            self::Ascenso => TipoDePolitica::Ascensos,
            self::Traslado => TipoDePolitica::Traslados,
            self::Permiso => TipoDePolitica::Permisos,
            self::Licencia => TipoDePolitica::Licencias,
            self::Vacaciones => TipoDePolitica::Vacaciones,
            self::Ausencia => TipoDePolitica::Ausencias,
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
