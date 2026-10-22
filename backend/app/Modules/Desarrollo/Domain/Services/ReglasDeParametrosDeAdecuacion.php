<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\PesosInvalidos;
use App\Modules\Desarrollo\Domain\Exceptions\UmbralInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;

/** Validación de los parámetros: tres pesos enteros no negativos que suman 100 y un umbral entero de al menos 1 mes. */
final class ReglasDeParametrosDeAdecuacion
{
    /**
     * @param  array{competencias: mixed, conocimientos: mixed, experiencia: mixed}  $pesos
     */
    public function validar(array $pesos, mixed $umbralMeses): ParametrosDeAdecuacion
    {
        foreach ($pesos as $peso) {
            if (! is_int($peso)) {
                throw PesosInvalidos::negativos();
            }
        }
        if (! is_int($umbralMeses)) {
            throw new UmbralInvalido;
        }

        return new ParametrosDeAdecuacion(
            new PesosPorDimension($pesos['competencias'], $pesos['conocimientos'], $pesos['experiencia']),
            $umbralMeses,
        );
    }

    /**
     * Campos que cambian entre dos parámetros, para el historial.
     *
     * @return list<array{campo: string, anterior: string, nuevo: string}>
     */
    public function cambios(ParametrosDeAdecuacion $actual, ParametrosDeAdecuacion $nuevo): array
    {
        $pares = [
            ['peso_competencias', $actual->pesos->competencias, $nuevo->pesos->competencias],
            ['peso_conocimientos', $actual->pesos->conocimientos, $nuevo->pesos->conocimientos],
            ['peso_experiencia', $actual->pesos->experiencia, $nuevo->pesos->experiencia],
            ['umbral_meses', $actual->umbralMeses, $nuevo->umbralMeses],
        ];

        $cambios = [];
        foreach ($pares as [$campo, $anterior, $valor]) {
            if ($anterior !== $valor) {
                $cambios[] = ['campo' => $campo, 'anterior' => (string) $anterior, 'nuevo' => (string) $valor];
            }
        }

        return $cambios;
    }
}
