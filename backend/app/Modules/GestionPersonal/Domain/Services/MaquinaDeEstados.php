<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion as E;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;

/** Transiciones válidas de cada categoría de actuación. Los estados terminales no tienen salida. PHP puro. */
final class MaquinaDeEstados
{
    /** @return list<E> */
    public function destinosDesde(TipoDeActuacion $tipo, E $desde): array
    {
        if ($tipo->esAusencia()) {
            return $desde === E::Informada ? [E::Revisada, E::Justificada] : [];
        }

        if ($tipo->esMovimiento()) {
            return match ($desde) {
                E::Presentada => [E::EnRevision, E::Rechazada, E::Aprobada, E::Programada],
                E::EnRevision => [E::Rechazada, E::Aprobada, E::Programada],
                E::Aprobada, E::Programada => [E::Aplicada, E::IncidenciaDeAplicacion],
                E::IncidenciaDeAplicacion => [E::Aplicada, E::IncidenciaDeAplicacion],
                default => [],
            };
        }

        return match ($desde) {
            E::Presentada => [E::EnRevision, E::Aprobada, E::Rechazada, E::Retirada],
            E::EnRevision => [E::Aprobada, E::Rechazada, E::Retirada],
            default => [],
        };
    }

    public function puede(TipoDeActuacion $tipo, E $desde, E $hacia): bool
    {
        return in_array($hacia, $this->destinosDesde($tipo, $desde), true);
    }

    /** Estado inicial al presentar. */
    public function inicial(TipoDeActuacion $tipo): E
    {
        return $tipo->esAusencia() ? E::Informada : E::Presentada;
    }
}
