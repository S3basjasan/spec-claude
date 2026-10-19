<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\FuenteDePlanesInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeEstadoDeAccion;
use DateTimeImmutable;

/** Lee del Plan de Formación vigente las acciones que consume Reportes y calcula su estado con la fecha dada. */
final class PlanesParaReportes implements FuenteDePlanesInterface
{
    public function __construct(
        private readonly PlanRepositoryInterface $planes,
        private readonly CalculadorDeEstadoDeAccion $estados,
    ) {}

    public function acciones(int $tenantId, DateTimeImmutable $hoy): array
    {
        $filas = [];
        foreach ($this->planes->planesConAcciones($tenantId, []) as $plan) {
            foreach ($plan['acciones'] as $accion) {
                $inicio = $accion['fecha_inicio'] === null ? null : new DateTimeImmutable($accion['fecha_inicio']);
                $fin = $accion['fecha_fin'] === null ? null : new DateTimeImmutable($accion['fecha_fin']);
                $filas[] = [
                    'accion_id' => (int) $accion['id'],
                    'plan_id' => (int) $plan['id'],
                    'plan_codigo' => (string) $plan['codigo'],
                    'plan_nombre' => (string) ($plan['nombre'] ?? ''),
                    'plan_estado' => (string) $plan['estado'],
                    'nombre' => (string) ($accion['nombre'] ?? ''),
                    'tipo' => $accion['tipo'],
                    'estado_accion' => $this->estados->calcular((bool) $accion['cancelada'], $inicio, $fin, $hoy)->value,
                    'fecha_inicio' => $accion['fecha_inicio'],
                    'fecha_fin' => $accion['fecha_fin'],
                    'horas' => $accion['horas'] === null ? null : (float) $accion['horas'],
                    'costo' => $accion['costo'] === null ? null : (float) $accion['costo'],
                    'cancelada' => (bool) $accion['cancelada'],
                    'competencia_ids' => array_values(array_map('intval', $accion['competencia_ids'] ?? [])),
                    'participante_ids' => array_values(array_map('intval', $accion['participante_ids'] ?? [])),
                ];
            }
        }

        return $filas;
    }

    public function planes(int $tenantId): array
    {
        return $this->planes->planesParaActividades($tenantId);
    }
}
