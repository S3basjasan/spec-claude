<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\CoordinadorDeCuentaPort;

/** Sin contrato con Identidad y Acceso la cuenta queda «pendiente»: nunca se afirma que fue deshabilitada. */
final class CoordinadorDeCuentaNoInstalado implements CoordinadorDeCuentaPort
{
    public function solicitar(int $tenantId, int $colaboradorId, string $fechaEfectiva): string
    {
        return 'Pendiente';
    }
}
