<?php

namespace App\Modules\GestionPersonal\Application\Ports;

interface LectorDeCapacidadesPort
{
    /** @param  'desarrollo'|'documentos'|'registrar_ingreso'|'exportar'  $capacidad */
    public function instalada(string $capacidad): bool;
}
