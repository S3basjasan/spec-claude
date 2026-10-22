<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;

/**
 * Cumplimiento por dimensión (FR-024): promedio, sobre los requisitos evaluados, de «valor de la persona ÷ requerido»
 * con tope de 1 por requisito. Una dimensión sin requisitos evaluados queda en `null` y se excluye del puntaje.
 */
final class CalculadorDeCumplimiento
{
    /**
     * Frente al puesto objetivo.
     *
     * @param  list<ResultadoDeAdecuacion>  $resultados
     * @return array<string, float|null> dimensión => 0..1 o null
     */
    public function porDimension(array $resultados): array
    {
        return $this->promediar($resultados, fn (ResultadoDeAdecuacion $r) => $r->cumplimientoObjetivo());
    }

    /**
     * Frente al puesto actual (informativo).
     *
     * @param  list<ResultadoDeAdecuacion>  $resultados
     * @return array<string, float|null>
     */
    public function porDimensionFrenteAlActual(array $resultados): array
    {
        return $this->promediar($resultados, fn (ResultadoDeAdecuacion $r) => $r->cumplimientoActual());
    }

    /**
     * @param  list<ResultadoDeAdecuacion>  $resultados
     * @param  callable(ResultadoDeAdecuacion): ?float  $valor
     * @return array<string, float|null>
     */
    private function promediar(array $resultados, callable $valor): array
    {
        $salida = [];
        foreach (DimensionDeAdecuacion::cases() as $dimension) {
            $valores = [];
            foreach ($resultados as $r) {
                if ($r->dimension === $dimension && ($v = $valor($r)) !== null) {
                    $valores[] = $v;
                }
            }
            $salida[$dimension->value] = $valores === [] ? null : array_sum($valores) / count($valores);
        }

        return $salida;
    }
}
