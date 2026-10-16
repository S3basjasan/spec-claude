<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;

/** GP-02 instala el registro de ingresos; Desarrollo, Documentos y Exportación siguen sin instalar. */
final class LectorDeCapacidadesGp02 implements LectorDeCapacidadesPort
{
    public function instalada(string $capacidad): bool
    {
        return $capacidad === 'registrar_ingreso' && (bool) config('hrms.gestion_personal.ingresos_habilitados', true);
    }
}
