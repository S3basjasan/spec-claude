<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeParametrosDeAdecuacion;

/**
 * Cambia los pesos y el umbral. Registra una fila de historial por campo modificado (usuario, fecha, valor anterior y
 * nuevo) en la misma transacción. Rige para las adecuaciones que se generen en adelante: las guardadas no se recalculan.
 */
final class ActualizarParametrosDeAdecuacion
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly ReglasDeParametrosDeAdecuacion $reglas,
        private readonly ObtenerParametrosDeAdecuacion $obtener,
    ) {}

    /**
     * @param  array{competencias: mixed, conocimientos: mixed, experiencia: mixed}  $pesos
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $actorId, array $pesos, mixed $umbralMeses): array
    {
        $nuevos = $this->reglas->validar($pesos, $umbralMeses);
        $cambios = $this->reglas->cambios($this->adecuaciones->obtenerParametros($tenantId), $nuevos);

        if ($cambios !== []) {
            $this->adecuaciones->guardarParametros($tenantId, $nuevos, $cambios, $actorId);
        }

        return $this->obtener->ejecutar($tenantId);
    }
}
