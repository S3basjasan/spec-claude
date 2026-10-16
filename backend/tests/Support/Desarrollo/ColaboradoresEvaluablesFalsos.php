<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;

/** Puerto falso de colaboradores evaluables para pruebas. */
final class ColaboradoresEvaluablesFalsos implements ColaboradoresEvaluablesInterface
{
    /**
     * @param  list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>  $evaluables
     * @param  list<array{colaborador_id: int, nombre: string, motivo: string}>  $excluidos
     * @param  array<int, list<int>>  $subunidades  unidad_id => ids de sus subunidades
     * @param  list<array{id: int, nombre: string, colaboradores_evaluables: int}>  $departamentos
     */
    public function __construct(
        public array $evaluables = [],
        public array $excluidos = [],
        public array $subunidades = [],
        public array $departamentos = [],
    ) {}

    public function evaluablesPorIds(int $tenantId, array $ids): array
    {
        return [
            'evaluables' => array_values(array_filter($this->evaluables, fn (array $e) => in_array($e['colaborador_id'], $ids, true))),
            'excluidos' => array_values(array_filter($this->excluidos, fn (array $e) => in_array($e['colaborador_id'], $ids, true))),
        ];
    }

    public function evaluablesDeUnidad(int $tenantId, int $unidadId): array
    {
        $unidades = [$unidadId, ...($this->subunidades[$unidadId] ?? [])];

        return [
            'evaluables' => array_values(array_filter($this->evaluables, fn (array $e) => in_array($e['unidad_id'], $unidades, true))),
            'excluidos' => $this->excluidos,
        ];
    }

    public function evaluables(int $tenantId): array
    {
        return $this->evaluables;
    }

    public function departamentosConEvaluables(int $tenantId): array
    {
        return $this->departamentos;
    }
}
