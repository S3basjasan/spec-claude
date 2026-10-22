<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\ExperienciaDelColaboradorInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Años de experiencia acumulada según las experiencias anteriores que registra Gestión de Personal (GP-06), sumados sin
 * contar dos veces los periodos superpuestos. Sin experiencias registradas o con la fuente caída no hay dato.
 */
final class ExperienciaDesdePersonal implements ExperienciaDelColaboradorInterface
{
    public function aniosDe(int $tenantId, array $colaboradorIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $colaboradorIds)));
        if ($ids === [] || ! config('desarrollo.adecuacion.experiencia_disponible', true)) {
            return array_fill_keys($ids, null);
        }

        try {
            $desarrollo = PersonalFacade::getDesarrollo($tenantId, $ids);
        } catch (Throwable) {
            return array_fill_keys($ids, null);
        }

        $salida = [];
        foreach ($ids as $id) {
            $experiencias = $desarrollo[$id]['experiencias'] ?? [];
            $salida[$id] = $experiencias === [] ? null : $this->anios($experiencias);
        }

        return $salida;
    }

    /** @param  list<array<string, mixed>>  $experiencias */
    private function anios(array $experiencias): ?float
    {
        $hoy = CarbonImmutable::today();
        $periodos = [];
        foreach ($experiencias as $e) {
            if (empty($e['fecha_inicio'])) {
                continue;
            }
            $inicio = CarbonImmutable::parse((string) $e['fecha_inicio'])->startOfDay();
            $fin = empty($e['fecha_finalizacion']) ? $hoy : CarbonImmutable::parse((string) $e['fecha_finalizacion'])->startOfDay();
            if ($fin->greaterThan($inicio)) {
                $periodos[] = [$inicio->getTimestamp(), $fin->getTimestamp()];
            }
        }

        if ($periodos === []) {
            return null;
        }

        usort($periodos, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $segundos = 0;
        [$desde, $hasta] = $periodos[0];
        foreach (array_slice($periodos, 1) as [$i, $f]) {
            if ($i <= $hasta) {
                $hasta = max($hasta, $f);

                continue;
            }
            $segundos += $hasta - $desde;
            [$desde, $hasta] = [$i, $f];
        }
        $segundos += $hasta - $desde;

        return round($segundos / (365.25 * 86400), 1);
    }
}
