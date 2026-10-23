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
        return $this->ultimaConBrechas($tenantId, $colaboradorId)['brechas'] ?? [];
    }

    /**
     * La DNF más reciente del colaborador con sus brechas «Con brecha» (para precargar un plan de desarrollo).
     *
     * @return array{dnf_id: int, fecha: string|null, brechas: list<array{competencia_id: int, nivel_requerido: string, nivel_actual: string|null, diferencia: int|null, prioridad: string|null}>}|null
     */
    public function ultimaConBrechas(int $tenantId, int $colaboradorId): ?array
    {
        $dnf = $this->repositorio->masRecienteDeColaborador($tenantId, $colaboradorId);
        $resultado = $dnf?->resultados[0] ?? null;
        if ($dnf === null || $resultado === null) {
            return null;
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

        return ['dnf_id' => (int) $dnf->id, 'fecha' => $dnf->creadoEn?->format('Y-m-d\TH:i:sP'), 'brechas' => $activas];
    }
}
