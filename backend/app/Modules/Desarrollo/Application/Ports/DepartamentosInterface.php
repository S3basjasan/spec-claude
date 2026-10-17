<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Departamentos (unidades organizacionales) de Estructura Empresarial. */
interface DepartamentosInterface
{
    /** @return list<array{id: int, nombre: string}> unidades activas */
    public function activos(): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => nombre (incluye inactivas)
     */
    public function porIds(array $ids): array;

    public function estaActivo(int $id): bool;
}
