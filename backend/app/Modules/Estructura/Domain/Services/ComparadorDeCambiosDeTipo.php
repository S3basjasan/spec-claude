<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;

/**
 * Compara dos estados de un tipo de unidad y devuelve las filas del historial (`nombre`, `nivel`). Puro: sin Laravel.
 * Al crear (`$anterior` nulo) hay una fila por cada dato inicial, con valor anterior nulo.
 */
final class ComparadorDeCambiosDeTipo
{
    /**
     * @param  array{nombre: string, nivel: int}|null  $anterior
     * @param  array{nombre: string, nivel: int}  $nuevo
     * @return array<int, CambioAuditado>
     */
    public function comparar(?array $anterior, array $nuevo): array
    {
        $cambios = [];
        foreach (['nombre' => (string) $nuevo['nombre'], 'nivel' => (string) $nuevo['nivel']] as $campo => $valor) {
            $previo = $anterior === null ? null : (string) $anterior[$campo];
            if ($previo !== $valor) {
                $cambios[] = new CambioAuditado($campo, $previo, $valor);
            }
        }

        return $cambios;
    }
}
