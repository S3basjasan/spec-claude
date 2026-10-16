<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;

/**
 * Brechas activas de un colaborador: las «Con brecha» de su DNF más reciente (individual o masiva). Las DNF anteriores
 * se conservan en el historial pero dejan de ser la fuente de las brechas activas.
 */
final class ObtenerBrechasActivas
{
    public function __construct(private readonly DnfRepositoryInterface $repositorio) {}

    /**
     * @return list<array{competencia_id: int, nivel_requerido: string, nivel_actual: string|null, diferencia: int|null, prioridad: string|null}>
     */
    public function ejecutar(int $tenantId, int $colaboradorId): array
    {
        $dnf = $this->repositorio->masRecienteDeColaborador($tenantId, $colaboradorId);
        $resultado = $dnf?->resultados[0] ?? null;
        if ($resultado === null) {
            return [];
        }

        $activas = [];
        foreach ($resultado->brechas as $brecha) {
            if ($brecha->estado === EstadoBrecha::ConBrecha) {
                $activas[] = [
                    'competencia_id' => $brecha->competenciaId,
                    'nivel_requerido' => $brecha->nivelRequerido->value,
                    'nivel_actual' => $brecha->nivelActual?->value,
                    'diferencia' => $brecha->diferencia,
                    'prioridad' => $brecha->prioridad?->value,
                ];
            }
        }

        return $activas;
    }
}
