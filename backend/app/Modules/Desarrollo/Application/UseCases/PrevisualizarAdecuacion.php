<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDiagnostico;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;

/** Calcula el diagnóstico de un colaborador frente a un puesto objetivo sin guardar nada. */
final class PrevisualizarAdecuacion
{
    public function __construct(
        private readonly ArmadorDeDiagnostico $diagnostico,
        private readonly ArmadorDeAdecuaciones $armador,
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $colaboradorId, ?int $inscripcionId, ?int $puestoObjetivoId, ?float $experienciaRequerida): array
    {
        $d = $this->diagnostico->armar($tenantId, $colaboradorId, $inscripcionId, $puestoObjetivoId, $experienciaRequerida);

        return $this->armador->diagnostico($d, $tenantId, $this->adecuaciones->obtenerParametros($tenantId));
    }
}
