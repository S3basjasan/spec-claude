<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Entities\ImpactoAccion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeRoi;
use Throwable;

/** Arma la evaluación de impacto del contrato: sus datos, el ROI de la acción y la auditoría con nombres de usuario. */
final class ArmadorDeImpacto
{
    public function __construct(
        private readonly CalculadorDeRoi $roi,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /**
     * @param  list<ImpactoAccion>  $impactos
     * @return array<int, string> usuario_id => nombre
     */
    public function nombresDe(array $impactos): array
    {
        $ids = [];
        foreach ($impactos as $i) {
            $ids[] = $i->creadoPor;
            $ids[] = $i->actualizadoPor;
        }
        try {
            return $ids === [] ? [] : $this->usuarios->nombresDe(array_values(array_unique($ids)));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, string>  $nombres
     * @return array<string, mixed>
     */
    public function armar(ImpactoAccion $i, ?float $costo, array $nombres): array
    {
        $roi = $this->roi->deAccion($i->beneficioEstimado, $costo);

        return [
            'plan_accion_id' => $i->planAccionId,
            'indicador' => $i->indicador,
            'unidad' => $i->unidad,
            'valor_antes' => $i->valorAntes,
            'valor_despues' => $i->valorDespues,
            'beneficio_estimado' => $i->beneficioEstimado,
            'justificacion' => $i->justificacion,
            'roi' => $roi === null ? null : round($roi, 1),
            'creado_por' => $nombres[$i->creadoPor] ?? null,
            'creado_en' => $i->creadoEn?->format(DATE_ATOM),
            'modificado_por' => $nombres[$i->actualizadoPor] ?? null,
            'modificado_en' => $i->actualizadoEn?->format(DATE_ATOM),
        ];
    }
}
