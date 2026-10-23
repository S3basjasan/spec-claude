<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDePdi;

/**
 * Resultado del plan al cerrar (FR-033): Brecha cerrada si todos los compromisos están Cerrada, No cerrada si ninguno lo
 * está y Parcial en cualquier otro caso.
 */
final class CalculadorDeResultadoDePdi
{
    /** @param  list<ResultadoDeCompromiso>  $resultados */
    public function calcular(array $resultados): ResultadoDePdi
    {
        $cerradas = count(array_filter($resultados, fn (ResultadoDeCompromiso $r) => $r === ResultadoDeCompromiso::Cerrada));

        if ($resultados !== [] && $cerradas === count($resultados)) {
            return ResultadoDePdi::BrechaCerrada;
        }

        return $cerradas === 0 ? ResultadoDePdi::NoCerrada : ResultadoDePdi::Parcial;
    }
}
