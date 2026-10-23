<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoAPdi;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;

/** Detalle de un plan; 404 si no existe o no es accesible para quien lo consulta. */
final class ObtenerPdi
{
    public function __construct(private readonly AccesoAPdi $acceso, private readonly ArmadorDeDetalleDePdi $armador) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $usuarioId, int $id, bool $esAdministrador): array
    {
        return $this->armador->detalle($this->acceso->cargar($tenantId, $usuarioId, $id, $esAdministrador), $esAdministrador, $usuarioId);
    }
}
