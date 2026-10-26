<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeActuacion: string
{
    case Presentada = 'Presentada';
    case EnRevision = 'EnRevision';
    case Aprobada = 'Aprobada';
    case Rechazada = 'Rechazada';
    case Retirada = 'Retirada';
    case Programada = 'Programada';
    case Aplicada = 'Aplicada';
    case IncidenciaDeAplicacion = 'IncidenciaDeAplicacion';
    case Informada = 'Informada';
    case Revisada = 'Revisada';
    case Justificada = 'Justificada';

    public function esTerminal(): bool
    {
        return in_array($this, [self::Rechazada, self::Retirada, self::Aplicada, self::Revisada, self::Justificada], true);
    }

    /** La solicitud admite aprobar, rechazar o retirar. */
    public function esResoluble(): bool
    {
        return $this === self::Presentada || $this === self::EnRevision;
    }

    /** Movimiento aprobado que todavía espera su aplicación (con o sin incidencia). */
    public function esPendienteDeAplicar(): bool
    {
        return in_array($this, [self::Aprobada, self::Programada, self::IncidenciaDeAplicacion], true);
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
