<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;

/** GP-03, GP-02 y Documentos instaladas: conserva el interruptor de ingresos; Exportación (activable con `hrms.gestion_personal.exportacion_habilitada`). */
final class LectorDeCapacidadesDesarrollo implements LectorDeCapacidadesPort
{
    public function __construct(private readonly LectorDeCapacidadesGp02 $ingresos) {}

    public function instalada(string $capacidad): bool
    {
        if ($capacidad === 'exportar') {
            return (bool) config('hrms.gestion_personal.exportacion_habilitada', true);
        }

        return in_array($capacidad, ['desarrollo', 'documentos'], true) || $this->ingresos->instalada($capacidad);
    }
}
