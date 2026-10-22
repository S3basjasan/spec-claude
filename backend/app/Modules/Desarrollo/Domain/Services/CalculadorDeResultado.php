<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;

/**
 * Único lugar donde la adecuación calcula la diferencia y el estado de un requisito. Los grados usan la escala
 * numérica de `NivelGrado` (D=1 … A=4), que no se vuelve a definir aquí. Diferencia = valor de la persona − requerido
 * por el puesto objetivo: negativa es brecha, cero cumple y positiva supera.
 */
final class CalculadorDeResultado
{
    public function paraGrado(
        DimensionDeAdecuacion $dimension,
        int $elementoId,
        string $nombre,
        ?NivelGrado $requeridoActual,
        NivelGrado $requeridoObjetivo,
        ?NivelGrado $valor,
    ): ResultadoDeAdecuacion {
        $diferencia = $valor === null ? null : (float) ($valor->valorNumerico() - $requeridoObjetivo->valorNumerico());

        return new ResultadoDeAdecuacion(
            $dimension, $dimension->value, $elementoId, $nombre,
            $requeridoActual, $requeridoObjetivo, $valor,
            null, null, null,
            $diferencia, $this->estadoDe($diferencia),
        );
    }

    public function paraExperiencia(?float $requeridoActualAnios, float $requeridoObjetivoAnios, ?float $valorAnios): ResultadoDeAdecuacion
    {
        $diferencia = $valorAnios === null ? null : round($valorAnios - $requeridoObjetivoAnios, 1);

        return new ResultadoDeAdecuacion(
            DimensionDeAdecuacion::Experiencia, null, null, null,
            null, null, null,
            $requeridoActualAnios, $requeridoObjetivoAnios, $valorAnios,
            $diferencia, $this->estadoDe($diferencia),
        );
    }

    private function estadoDe(?float $diferencia): EstadoDeResultado
    {
        return match (true) {
            $diferencia === null => EstadoDeResultado::SinEvaluacion,
            $diferencia < 0 => EstadoDeResultado::ConBrecha,
            $diferencia > 0 => EstadoDeResultado::Supera,
            default => EstadoDeResultado::Cumple,
        };
    }
}
