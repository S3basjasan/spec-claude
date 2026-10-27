<?php

namespace App\Modules\GestionPersonal\Application\Ports;

/** Permiso específico «consultar entrevistas de salida», concedido por el componente de acceso (no por esta funcionalidad). */
interface PermisoDeEntrevistasPort
{
    public function puede(int $userId, int $tenantId): bool;
}
