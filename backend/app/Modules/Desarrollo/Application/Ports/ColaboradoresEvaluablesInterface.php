<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Colaboradores que se pueden evaluar (vínculo vigente, puesto y departamento) y los que se excluyen.
 * Lo aportará Gestión de Personal (GP-02, aún no implementada): el adaptador actual es provisional.
 *
 * Forma del resultado: `evaluables` = [{colaborador_id, nombre, puesto_id, unidad_id}] y
 * `excluidos` = [{colaborador_id, nombre, motivo}] con motivo SinPuesto | SinVinculoVigente.
 */
interface ColaboradoresEvaluablesInterface
{
    /**
     * @param  list<int>  $ids
     * @return array{evaluables: list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>, excluidos: list<array{colaborador_id: int, nombre: string, motivo: string}>}
     */
    public function evaluablesPorIds(int $tenantId, array $ids): array;

    /**
     * Incluye los colaboradores de las subunidades de la unidad indicada.
     *
     * @return array{evaluables: list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>, excluidos: list<array{colaborador_id: int, nombre: string, motivo: string}>}
     */
    public function evaluablesDeUnidad(int $tenantId, int $unidadId): array;

    /**
     * Todos los colaboradores evaluables del tenant (selector del paso 1).
     *
     * @return list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>
     */
    public function evaluables(int $tenantId): array;

    /**
     * Unidades (departamentos) con la cantidad de colaboradores evaluables que incluyen con sus subunidades.
     *
     * @return list<array{id: int, nombre: string, colaboradores_evaluables: int}>
     */
    public function departamentosConEvaluables(int $tenantId): array;
}
