<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\Brecha;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;

/**
 * Único lugar donde se calcula la brecha. Escala numérica D=1, C=2, B=3, A=4.
 * Diferencia = actual - requerido; Brecha % = (requerido - actual) / requerido x 100 solo si actual < requerido.
 */
final class CalculadorDeBrecha
{
    public function calcular(int $competenciaId, TipoCompetenciaDnf $tipo, NivelGrado $requerido, ?NivelGrado $actual): Brecha
    {
        if ($actual === null) {
            return new Brecha($competenciaId, $tipo, $requerido, null, null, null, null, EstadoBrecha::SinEvaluacion);
        }

        $diferencia = $actual->valorNumerico() - $requerido->valorNumerico();

        if ($diferencia >= 0) {
            $estado = $diferencia === 0 ? EstadoBrecha::Cumple : EstadoBrecha::Supera;

            return new Brecha($competenciaId, $tipo, $requerido, $actual, $diferencia, 0.0, null, $estado);
        }

        $porcentaje = round(($requerido->valorNumerico() - $actual->valorNumerico()) / $requerido->valorNumerico() * 100, 2);

        return new Brecha(
            $competenciaId,
            $tipo,
            $requerido,
            $actual,
            $diferencia,
            $porcentaje,
            $this->prioridadDe($diferencia, $tipo),
            EstadoBrecha::ConBrecha,
        );
    }

    private function prioridadDe(int $diferencia, TipoCompetenciaDnf $tipo): PrioridadBrecha
    {
        return match (true) {
            $diferencia <= -3 => PrioridadBrecha::Critica,
            $diferencia === -2 => PrioridadBrecha::Alta,
            default => $tipo === TipoCompetenciaDnf::Cardinal ? PrioridadBrecha::Media : PrioridadBrecha::Baja,
        };
    }
}
