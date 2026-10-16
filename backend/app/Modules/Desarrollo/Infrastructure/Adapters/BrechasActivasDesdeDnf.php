<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\UseCases\ObtenerBrechasActivas;

/**
 * Brechas activas del catálogo formativo (puerto de la feature 005) alimentadas por la DNF: traduce el usuario a su
 * ficha de colaborador y devuelve las brechas «Con brecha» de su DNF más reciente.
 */
final class BrechasActivasDesdeDnf implements BrechasActivasInterface
{
    public function __construct(
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly ObtenerBrechasActivas $brechasActivas,
    ) {}

    public function brechasDe(int $usuarioId): array
    {
        $cuenta = $this->cuentas->cuentaDe($usuarioId);
        if ($cuenta === null) {
            return [];
        }

        return array_map(
            fn (array $b) => ['competencia_id' => $b['competencia_id'], 'nivel_requerido' => $b['nivel_requerido']],
            $this->brechasActivas->ejecutar($cuenta['tenant_id'], $cuenta['colaborador_id']),
        );
    }
}
