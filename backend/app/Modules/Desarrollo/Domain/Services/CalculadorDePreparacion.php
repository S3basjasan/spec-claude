<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;

/**
 * Clasifica el resultado completo (FR-016): Incompleta si algún requisito quedó sin evaluar; si no, Lista sin brechas,
 * En desarrollo si todas las brechas se cierran en el umbral o menos, y No lista si alguna lo supera. Una brecha sin
 * tiempo de cierre aún no supera el umbral (solo ocurre en la previsualización).
 */
final class CalculadorDePreparacion
{
    /** @param  list<ResultadoDeAdecuacion>  $resultados */
    public function calcular(array $resultados, int $umbralMeses): Preparacion
    {
        foreach ($resultados as $r) {
            if ($r->estado === EstadoDeResultado::SinEvaluacion) {
                return Preparacion::Incompleta;
            }
        }

        $brechas = array_values(array_filter($resultados, fn (ResultadoDeAdecuacion $r) => $r->esBrecha()));
        if ($brechas === []) {
            return Preparacion::Lista;
        }

        foreach ($brechas as $b) {
            if (($b->tiempoCierreMeses ?? 0) > $umbralMeses) {
                return Preparacion::NoLista;
            }
        }

        return Preparacion::EnDesarrollo;
    }
}
