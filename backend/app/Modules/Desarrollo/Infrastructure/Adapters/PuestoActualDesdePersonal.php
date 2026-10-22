<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\PuestoActualDelColaboradorInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Throwable;

/** Puesto actual de los colaboradores por la Facade pública de Gestión de Personal; si falla, queda sin dato. */
final class PuestoActualDesdePersonal implements PuestoActualDelColaboradorInterface
{
    public function puestoActualDe(int $tenantId, array $colaboradorIds, string $fecha): array
    {
        $ids = array_values(array_unique(array_map('intval', $colaboradorIds)));
        if ($ids === []) {
            return [];
        }

        try {
            $puestos = PersonalFacade::getPuestoActual($tenantId, $ids, $fecha);
        } catch (Throwable) {
            return array_fill_keys($ids, null);
        }

        $salida = [];
        foreach ($ids as $id) {
            $salida[$id] = isset($puestos[$id]) ? (int) $puestos[$id] : null;
        }

        return $salida;
    }
}
