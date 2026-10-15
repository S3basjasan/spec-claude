<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDeUnidad;

/**
 * Compara dos estados de una unidad y devuelve las filas que deben quedar en el historial. Puro: sin Laravel.
 *
 * Una fila por campo cambiado (`nombre`, `tipo_unidad_id`, `unidad_padre_id`, `estado`, `fecha_supresion`). Al crear
 * (`$anterior` nulo) hay una fila por cada dato inicial con valor, con valor anterior nulo. Las referencias se guardan
 * como el id del registro relacionado y las fechas como `YYYY-MM-DD`.
 */
final class ComparadorDeCambiosDeUnidad
{
    /**
     * @return array<int, CambioAuditado>
     */
    public function comparar(?InstantaneaDeUnidad $anterior, InstantaneaDeUnidad $nuevo): array
    {
        $antes = $anterior === null ? [] : $this->valores($anterior);
        $despues = $this->valores($nuevo);

        $cambios = [];
        foreach ($despues as $campo => $valor) {
            $previo = $antes[$campo] ?? null;
            if ($previo !== $valor) {
                $cambios[] = new CambioAuditado($campo, $previo, $valor);
            }
        }

        return $cambios;
    }

    /**
     * @return array<string, string|null>
     */
    private function valores(InstantaneaDeUnidad $unidad): array
    {
        return [
            'nombre' => $unidad->nombre,
            'tipo_unidad_id' => (string) $unidad->tipoUnidadId,
            'unidad_padre_id' => $unidad->unidadPadreId === null ? null : (string) $unidad->unidadPadreId,
            'estado' => $unidad->estado->value,
            'fecha_supresion' => $unidad->fechaSupresion,
        ];
    }
}
