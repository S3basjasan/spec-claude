<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;

/** Desarrollo, Documentos, Ingreso y Exportación aún no están implementadas. */
final class LectorDeCapacidadesNoInstalado implements LectorDeCapacidadesPort
{
    public function instalada(string $capacidad): bool
    {
        return false;
    }
}
