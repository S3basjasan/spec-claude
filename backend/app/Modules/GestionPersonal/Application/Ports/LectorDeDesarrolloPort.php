<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/**
 * Lectura de desarrollo profesional por lote (GP-06). Los fallos se devuelven como Lectura::noDisponible().
 */
interface LectorDeDesarrolloPort
{
    /**
     * @param  list<int>  $colaboradorIds
     * @return Lectura Datos: [colaborador_id => {formaciones, experiencias, movilidad}] sin `version` ni auditoría.
     */
    public function deColaboradores(int $tenantId, array $colaboradorIds): Lectura;
}
