<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDePosicion;

/**
 * Compara dos estados de una posición y devuelve las filas que deben quedar en el historial. Puro: sin Laravel.
 *
 * Una fila por campo cambiado (`puesto_id` —solo se registra al crear—, `unidad_organizacional_id`, `posicion_jefe_id`,
 * `modalidad` y `estado`). Al crear (`$anterior` nulo) hay una fila por cada dato inicial con valor, con valor anterior
 * nulo. Las referencias se guardan como el id del registro relacionado.
 */
final class ComparadorDeCambiosDePosicion
{
    /**
     * @return array<int, CambioAuditado>
     */
    public function comparar(?InstantaneaDePosicion $anterior, InstantaneaDePosicion $nuevo): array
    {
        $antes = $anterior === null ? [] : $this->valores($anterior);
        $despues = $this->valores($nuevo);

        $cambios = [];
        foreach ($despues as $campo => $valor) {
            // El puesto no cambia después de crear: solo se registra el valor inicial.
            if ($campo === 'puesto_id' && $anterior !== null) {
                continue;
            }
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
    private function valores(InstantaneaDePosicion $posicion): array
    {
        return [
            'puesto_id' => (string) $posicion->puestoId,
            'unidad_organizacional_id' => (string) $posicion->unidadId,
            'posicion_jefe_id' => $posicion->supervisorId === null ? null : (string) $posicion->supervisorId,
            'modalidad' => $posicion->modalidad->value,
            'estado' => $posicion->estado->value,
        ];
    }
}
