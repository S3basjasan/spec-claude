<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\FuenteDePlanesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeImpacto;
use App\Modules\Desarrollo\Domain\Entities\ImpactoAccion;
use App\Modules\Desarrollo\Domain\Exceptions\AccionNoEncontradaParaReporte;
use App\Modules\Desarrollo\Domain\Exceptions\ImpactoNoPermitido;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeImpacto;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;

/** Crea o reemplaza la evaluación de impacto de una acción Finalizada (aunque su plan esté Cerrado). */
final class RegistrarImpacto
{
    public function __construct(
        private readonly FuenteDePlanesInterface $planes,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly ReglasDeImpacto $reglas,
        private readonly ArmadorDeImpacto $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  indicador, unidad, valor_antes, valor_despues, beneficio_estimado, justificacion
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $planAccionId, array $datos): array
    {
        $accion = null;
        foreach ($this->planes->acciones($tenantId, $this->reloj->hoy($usuarioId)) as $a) {
            if ($a['accion_id'] === $planAccionId) {
                $accion = $a;
                break;
            }
        }
        if ($accion === null) {
            throw new AccionNoEncontradaParaReporte;
        }
        if (! in_array($accion['plan_estado'], ['Ejecutando', 'Cerrado'], true)) {
            throw new ImpactoNoPermitido;
        }
        $this->reglas->validarAccion(EstadoAccion::from($accion['estado_accion']));
        $this->reglas->validar($datos);

        $guardado = $this->reportes->guardarImpacto(new ImpactoAccion(
            null, $tenantId, $planAccionId,
            trim((string) $datos['indicador']), trim((string) $datos['unidad']),
            (float) $datos['valor_antes'], (float) $datos['valor_despues'], round((float) $datos['beneficio_estimado'], 2),
            trim((string) $datos['justificacion']), $usuarioId, $usuarioId,
        ), $usuarioId);

        return $this->armador->armar($guardado, $accion['costo'], $this->armador->nombresDe([$guardado]));
    }
}
