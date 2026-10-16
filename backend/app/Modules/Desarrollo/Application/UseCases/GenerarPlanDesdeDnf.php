<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Domain\Exceptions\DnfNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\DnfSinBrechas;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoDisponible;
use App\Modules\Desarrollo\Domain\Exceptions\PlanYaGenerado;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Solicita al módulo Plan de Formación un único borrador por DNF con las competencias con brecha de todos los
 * colaboradores evaluados, y guarda la referencia con quién lo generó y cuándo. La asignación es atómica: una DNF solo
 * puede quedar con un plan.
 */
final class GenerarPlanDesdeDnf
{
    public function __construct(
        private readonly DnfRepositoryInterface $repositorio,
        private readonly PlanDeFormacionInterface $planes,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /**
     * @return array{plan_formacion_id: int, generado_por: array{id: int, nombre: string}, generado_en: string}
     *
     * @throws DnfNoEncontrada
     * @throws DnfSinBrechas
     * @throws PlanYaGenerado
     * @throws PlanNoDisponible
     */
    public function ejecutar(int $tenantId, int $actorId, int $dnfId): array
    {
        $dnf = $this->repositorio->obtener($tenantId, $dnfId);
        if ($dnf === null) {
            throw new DnfNoEncontrada;
        }
        if ($dnf->cantidadBrechas() === 0) {
            throw new DnfSinBrechas;
        }
        if ($dnf->tienePlan()) {
            throw new PlanYaGenerado;
        }

        $brechas = [];
        foreach ($dnf->resultados as $resultado) {
            foreach ($resultado->brechas as $brecha) {
                if ($brecha->estado === EstadoBrecha::ConBrecha) {
                    $brechas[] = [
                        'colaborador_id' => $resultado->colaboradorId,
                        'competencia_id' => $brecha->competenciaId,
                        'nivel_requerido' => $brecha->nivelRequerido->value,
                        'nivel_actual' => $brecha->nivelActual?->value,
                        'diferencia' => $brecha->diferencia,
                        'prioridad' => $brecha->prioridad?->value,
                    ];
                }
            }
        }

        try {
            $planId = $this->planes->crearBorrador($tenantId, $actorId, $dnfId, $brechas);
        } catch (PlanNoDisponible $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('El módulo Plan de Formación falló al crear el borrador.', ['excepcion' => $e::class]);

            throw new PlanNoDisponible;
        }

        $momento = new DateTimeImmutable;
        if (! $this->repositorio->asignarPlan($tenantId, $dnfId, $planId, $actorId, $momento)) {
            throw new PlanYaGenerado;
        }

        return [
            'plan_formacion_id' => $planId,
            'generado_por' => ['id' => $actorId, 'nombre' => $this->nombreDe($actorId)],
            'generado_en' => $momento->format(DATE_ATOM),
        ];
    }

    private function nombreDe(int $usuarioId): string
    {
        try {
            return $this->usuarios->nombresDe([$usuarioId])[$usuarioId] ?? 'No disponible';
        } catch (Throwable) {
            return 'No disponible';
        }
    }
}
