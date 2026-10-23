<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Brechas desde las que se precarga un plan de desarrollo: la última DNF del colaborador (tipo Mejora) o su última
 * adecuación a un puesto objetivo (tipos Carrera y Talento). Solo lectura.
 */
interface BrechasParaPdiInterface
{
    /**
     * @return array{origen: array{tipo: string, id: int, fecha: string|null}, brechas: list<array{competencia_id: int, nivel_actual: string|null, nivel_objetivo: string}>}|null
     */
    public function ultimaDnf(int $tenantId, int $colaboradorId): ?array;

    /**
     * @return array{origen: array{tipo: string, id: int, fecha: string|null}, brechas: list<array{dimension: string, tipo_elemento: string|null, elemento_id: int|null, nombre: string|null, nivel_actual: string|null, nivel_objetivo: string|null, valor_anios: float|null, requerido_anios: float|null}>}|null
     */
    public function ultimaAdecuacion(int $tenantId, int $colaboradorId, int $puestoObjetivoId): ?array;
}
