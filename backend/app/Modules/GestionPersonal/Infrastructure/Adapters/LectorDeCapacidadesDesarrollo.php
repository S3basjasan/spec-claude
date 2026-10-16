<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;

/** GP-03 y GP-02 instaladas: conserva el interruptor de ingresos; Documentos y Exportación pendientes. */
final class LectorDeCapacidadesDesarrollo implements LectorDeCapacidadesPort
{
    public function __construct(private readonly LectorDeCapacidadesGp02 $ingresos) {}

    public function instalada(string $capacidad): bool
    {
        return $capacidad === 'desarrollo' || $this->ingresos->instalada($capacidad);
    }
}
